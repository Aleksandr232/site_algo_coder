<?php

function quantlab_ready_path(): string
{
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'ready';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'list.json';
}

function quantlab_ready_uploads_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ready';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function quantlab_ready_venues(): array
{
    return [
        'finam' => 'Финам / MOEX',
        'tinkoff' => 'Тинькофф Инвестиции',
        'bybit' => 'Bybit',
        'okx' => 'OKX',
        'binance' => 'Binance',
        'multi' => 'Несколько площадок',
    ];
}

function quantlab_ready_normalize(array $row): array
{
    $venue = (string) ($row['venue'] ?? 'finam');
    if (!isset(quantlab_ready_venues()[$venue])) {
        $venue = 'finam';
    }
    $status = (($row['status'] ?? '') === 'hidden') ? 'hidden' : 'visible';
    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'status' => $status,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'price' => (string) ($row['price'] ?? ''),
        'venue' => $venue,
        'image' => (string) ($row['image'] ?? ''),
        'keywords' => (string) ($row['keywords'] ?? ''),
        'seo_title' => (string) ($row['seo_title'] ?? ''),
        'seo_description' => (string) ($row['seo_description'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function quantlab_ready_sort(array $items): array
{
    usort($items, static function ($a, $b) {
        $d = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        return $d !== 0 ? $d : strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });
    return $items;
}

function quantlab_ready_read_file(): array
{
    $path = quantlab_ready_path();
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }
    return quantlab_ready_sort(array_map('quantlab_ready_normalize', $decoded));
}

function quantlab_ready_write_file(array $items): void
{
    file_put_contents(
        quantlab_ready_path(),
        json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_ready_insert_row(PDO $pdo, array $row): void
{
    $st = $pdo->prepare(
        'INSERT INTO ready_robots (slug, status, sort_order, title, description, price, venue, image, keywords, seo_title, seo_description, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            status=VALUES(status), sort_order=VALUES(sort_order), title=VALUES(title),
            description=VALUES(description), price=VALUES(price), venue=VALUES(venue),
            image=VALUES(image), keywords=VALUES(keywords), seo_title=VALUES(seo_title),
            seo_description=VALUES(seo_description), updated_at=VALUES(updated_at)'
    );
    $st->execute([
        $row['slug'],
        $row['status'],
        $row['sort_order'],
        $row['title'],
        $row['description'],
        $row['price'],
        $row['venue'],
        $row['image'] !== '' ? $row['image'] : null,
        $row['keywords'] !== '' ? $row['keywords'] : null,
        $row['seo_title'] !== '' ? $row['seo_title'] : null,
        $row['seo_description'] !== '' ? $row['seo_description'] : null,
        quantlab_dt_sql($row['created_at']) ?: date('Y-m-d H:i:s'),
        quantlab_dt_sql($row['updated_at']) ?: date('Y-m-d H:i:s'),
    ]);
}

function quantlab_ready_all(): array
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $rows = $pdo->query('SELECT * FROM ready_robots ORDER BY sort_order ASC, title ASC')->fetchAll();
        return array_map('quantlab_ready_normalize', $rows ?: []);
    }
    return quantlab_ready_read_file();
}

function quantlab_ready_visible(): array
{
    return array_values(array_filter(quantlab_ready_all(), static function ($row) {
        return ($row['status'] ?? '') === 'visible';
    }));
}

function quantlab_ready_load(string $slug): ?array
{
    if (!quantlab_is_slug($slug)) {
        return null;
    }
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('SELECT * FROM ready_robots WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ? quantlab_ready_normalize($row) : null;
    }
    foreach (quantlab_ready_read_file() as $item) {
        if ($item['slug'] === $slug) {
            return $item;
        }
    }
    return null;
}

function quantlab_ready_unique_slug(string $slug, ?string $ignore = null): string
{
    $base = quantlab_slugify($slug);
    if ($base === '' || in_array($base, ['index', 'view', 'admin'], true)) {
        $base = 'robot';
    }
    $try = $base;
    $n = 2;
    while (true) {
        $taken = quantlab_ready_load($try);
        if (!$taken || $try === $ignore) {
            return $try;
        }
        $try = $base . '-' . $n;
        $n++;
    }
}

function quantlab_ready_delete_image(?string $path): void
{
    if (!$path) {
        return;
    }
    if (!preg_match('#^/uploads/ready/([a-zA-Z0-9._-]+)$#', $path, $match)) {
        return;
    }
    $file = quantlab_ready_uploads_dir() . DIRECTORY_SEPARATOR . $match[1];
    if (is_file($file)) {
        unlink($file);
    }
}

function quantlab_ready_handle_image(?string $current, array $file, bool $remove): ?string
{
    if ($remove && $current) {
        quantlab_ready_delete_image($current);
        $current = null;
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return $current;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Не удалось загрузить картинку');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Картинка больше 5 МБ');
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($file['tmp_name']);
    }
    if (!isset($allowed[$mime])) {
        $info = @getimagesize($file['tmp_name']);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    }
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('Нужен JPG, PNG, WEBP или GIF');
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = quantlab_ready_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new InvalidArgumentException('Не удалось сохранить картинку');
    }
    if ($current) {
        quantlab_ready_delete_image($current);
    }
    return '/uploads/ready/' . $name;
}

function quantlab_ready_save(array $input, ?string $currentSlug = null): array
{
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Укажите название робота');
    }
    $description = trim((string) ($input['description'] ?? ''));
    if ($description === '') {
        throw new InvalidArgumentException('Добавьте описание');
    }
    $price = quantlab_ready_price_label(trim((string) ($input['price'] ?? '')));
    if ($price === '') {
        throw new InvalidArgumentException('Укажите цену');
    }
    $venue = (string) ($input['venue'] ?? 'finam');
    if (!isset(quantlab_ready_venues()[$venue])) {
        throw new InvalidArgumentException('Выберите площадку');
    }
    $image = trim((string) ($input['image'] ?? ''));
    $keywords = function_exists('quantlab_blog_normalize_keywords')
        ? quantlab_blog_normalize_keywords((string) ($input['keywords'] ?? ''))
        : trim((string) ($input['keywords'] ?? ''));
    $seoTitle = trim((string) ($input['seo_title'] ?? ''));
    $seoDescription = trim((string) ($input['seo_description'] ?? ''));

    $slugSource = trim((string) ($input['slug'] ?? ''));
    if ($slugSource === '') {
        $slugSource = $title;
    }
    $slug = quantlab_ready_unique_slug($slugSource, $currentSlug);
    if (!quantlab_is_slug($slug)) {
        throw new InvalidArgumentException('Слаг только латиница, цифры и дефис');
    }

    $existing = $currentSlug ? quantlab_ready_load($currentSlug) : null;
    $now = date('c');
    $row = quantlab_ready_normalize([
        'slug' => $slug,
        'status' => !empty($input['visible']) ? 'visible' : 'hidden',
        'sort_order' => (int) ($input['sort_order'] ?? ($existing['sort_order'] ?? time())),
        'title' => $title,
        'description' => $description,
        'price' => $price,
        'venue' => $venue,
        'image' => $image,
        'keywords' => $keywords,
        'seo_title' => $seoTitle,
        'seo_description' => $seoDescription,
        'created_at' => $existing['created_at'] ?? $now,
        'updated_at' => $now,
    ]);

    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        if ($currentSlug && $currentSlug !== $slug) {
            $pdo->prepare('DELETE FROM ready_robots WHERE slug = ?')->execute([$currentSlug]);
        }
        try {
            quantlab_ready_insert_row($pdo, $row);
        } catch (Throwable $e) {
            $st = $pdo->prepare(
                'INSERT INTO ready_robots (slug, status, sort_order, title, description, price, venue, image, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    status=VALUES(status), sort_order=VALUES(sort_order), title=VALUES(title),
                    description=VALUES(description), price=VALUES(price), venue=VALUES(venue),
                    image=VALUES(image), updated_at=VALUES(updated_at)'
            );
            $st->execute([
                $row['slug'],
                $row['status'],
                $row['sort_order'],
                $row['title'],
                $row['description'],
                $row['price'],
                $row['venue'],
                $row['image'] !== '' ? $row['image'] : null,
                quantlab_dt_sql($row['created_at']) ?: date('Y-m-d H:i:s'),
                quantlab_dt_sql($row['updated_at']) ?: date('Y-m-d H:i:s'),
            ]);
        }
    } else {
        $items = quantlab_ready_read_file();
        $items = array_values(array_filter($items, static function ($item) use ($currentSlug, $slug) {
            return $item['slug'] !== $currentSlug && $item['slug'] !== $slug;
        }));
        $items[] = $row;
        quantlab_ready_write_file(quantlab_ready_sort($items));
    }
    if ($currentSlug && $currentSlug !== $slug) {
        quantlab_ready_remove_public_dir($currentSlug);
    }
    quantlab_ready_write_public_stub($slug);
    if (function_exists('quantlab_write_seo_files')) {
        quantlab_write_seo_files();
    }
    if ($row['status'] === 'visible' && function_exists('quantlab_ping_search_engines')) {
        quantlab_ping_search_engines();
    }
    return $row;
}

function quantlab_ready_delete(string $slug): void
{
    $row = quantlab_ready_load($slug);
    if ($row && !empty($row['image'])) {
        quantlab_ready_delete_image($row['image']);
    }
    quantlab_ready_remove_public_dir($slug);
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->prepare('DELETE FROM ready_robots WHERE slug = ?')->execute([$slug]);
        if (function_exists('quantlab_write_seo_files')) {
            quantlab_write_seo_files();
        }
        return;
    }
    $items = array_values(array_filter(quantlab_ready_read_file(), static function ($item) use ($slug) {
        return $item['slug'] !== $slug;
    }));
    quantlab_ready_write_file($items);
    if (function_exists('quantlab_write_seo_files')) {
        quantlab_write_seo_files();
    }
}

function quantlab_ready_set_status(string $slug, string $status): void
{
    $row = quantlab_ready_load($slug);
    if (!$row) {
        return;
    }
    $row['status'] = $status === 'hidden' ? 'hidden' : 'visible';
    $row['updated_at'] = date('c');
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->prepare('UPDATE ready_robots SET status = ?, updated_at = ? WHERE slug = ?')->execute([
            $row['status'],
            date('Y-m-d H:i:s'),
            $slug,
        ]);
        if (function_exists('quantlab_write_seo_files')) {
            quantlab_write_seo_files();
        }
        return;
    }
    $items = [];
    foreach (quantlab_ready_read_file() as $item) {
        $items[] = $item['slug'] === $slug ? $row : $item;
    }
    quantlab_ready_write_file($items);
    if (function_exists('quantlab_write_seo_files')) {
        quantlab_write_seo_files();
    }
}

function quantlab_ready_move(string $slug, string $dir): void
{
    $items = quantlab_ready_all();
    $index = null;
    foreach ($items as $i => $item) {
        if ($item['slug'] === $slug) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $swap = $dir === 'up' ? $index - 1 : $index + 1;
    if (!isset($items[$swap])) {
        return;
    }
    $tmp = $items[$index]['sort_order'];
    $items[$index]['sort_order'] = $items[$swap]['sort_order'];
    $items[$swap]['sort_order'] = $tmp;
    if ($items[$index]['sort_order'] === $items[$swap]['sort_order']) {
        $items[$index]['sort_order'] = ($index + 1) * 10;
        $items[$swap]['sort_order'] = ($swap + 1) * 10;
    }
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('UPDATE ready_robots SET sort_order = ?, updated_at = ? WHERE slug = ?');
        $now = date('Y-m-d H:i:s');
        $st->execute([$items[$index]['sort_order'], $now, $items[$index]['slug']]);
        $st->execute([$items[$swap]['sort_order'], $now, $items[$swap]['slug']]);
        return;
    }
    quantlab_ready_write_file($items);
}

function quantlab_ready_public_dir(string $slug): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'robots' . DIRECTORY_SEPARATOR . $slug;
}

function quantlab_ready_write_public_stub(string $slug): void
{
    if (!quantlab_is_slug($slug) || in_array($slug, ['index', 'view', 'admin'], true)) {
        return;
    }
    $dir = quantlab_ready_public_dir($slug);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $php = "<?php\n"
        . "declare(strict_types=1);\n"
        . "\$quantlab_slug = " . var_export($slug, true) . ";\n"
        . "require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';\n"
        . "quantlab_render_ready_page(\$quantlab_slug);\n";
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.php', $php, LOCK_EX);
}

function quantlab_ready_remove_public_dir(string $slug): void
{
    $dir = quantlab_ready_public_dir($slug);
    $file = $dir . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($file)) {
        unlink($file);
    }
    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function quantlab_ready_sync_public(): void
{
    foreach (quantlab_ready_all() as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        quantlab_ready_write_public_stub($slug);
    }
}

function quantlab_ready_seo_title(array $row): string
{
    $custom = trim((string) ($row['seo_title'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    $title = trim((string) ($row['title'] ?? 'Торговый робот'));
    return $title . ' — готовый торговый робот | AM QuantLab';
}

function quantlab_ready_seo_description(array $row): string
{
    $custom = trim((string) ($row['seo_description'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    $desc = trim((string) ($row['description'] ?? ''));
    $price = quantlab_ready_price_label((string) ($row['price'] ?? ''));
    $venues = quantlab_ready_venues();
    $venue = $venues[$row['venue'] ?? ''] ?? '';
    $base = $desc !== '' ? $desc : 'Готовый торговый робот AM QuantLab.';
    $tail = trim($venue . ($price !== '' ? ' · ' . $price : ''));
    $text = $tail !== '' ? $base . ' ' . $tail : $base;
    return function_exists('quantlab_clip') ? quantlab_clip($text, 160) : $text;
}

function quantlab_ready_price_number(string $price): ?string
{
    if (!preg_match('/(\d[\d\s\x{00A0}]*)/u', $price, $match)) {
        return null;
    }
    $num = preg_replace('/\s+/u', '', $match[1]);
    return $num !== '' ? $num : null;
}

function quantlab_ready_price_label(string $price): string
{
    $price = trim(preg_replace('/\s+/u', ' ', $price) ?? $price);
    if ($price === '') {
        return '';
    }
    $num = quantlab_ready_price_number($price);
    if ($num === null) {
        return $price;
    }
    $prefix = preg_match('/^\s*от\b/iu', $price) ? 'от ' : '';
    return $prefix . number_format((int) $num, 0, '', ' ') . ' ₽';
}

function quantlab_ready_description_html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '<p>Описание появится позже.</p>';
    }
    $parts = preg_split("/\n\s*\n/", $text) ?: [$text];
    $html = '';
    foreach ($parts as $part) {
        $html .= '<p>' . nl2br(quantlab_h($part), false) . '</p>';
    }
    return $html;
}

function quantlab_ready_url(string $slug): string
{
    return quantlab_public_path('robots/' . $slug);
}

function quantlab_render_ready_lead_form(array $row, bool $sent = false): void
{
    $slug = quantlab_h($row['slug']);
    ?>
        <form class="glass pad form js-ready-form" id="order" action="/api/lead.php" method="post">
          <input type="hidden" name="market" value="ready" />
          <input type="hidden" name="robot_slug" value="<?= $slug ?>" />
          <p class="eyebrow">Заявка</p>
          <h2>Оставить заявку</h2>
          <p>Имя и Telegram или email. Письмо придёт нам, свяжемся и подключим робота.</p>
          <label class="hp" aria-hidden="true">
            Сайт
            <input type="text" name="website" tabindex="-1" autocomplete="off" />
          </label>
          <label>
            Имя
            <input type="text" name="name" required placeholder="Как к вам обращаться" />
          </label>
          <label>
            Telegram или email
            <input type="text" name="contact" required placeholder="@username или mail@mail.ru" />
          </label>
          <label>
            Комментарий
            <textarea name="message" rows="3" placeholder="Счёт, площадка, когда удобно подключить"></textarea>
          </label>
          <button class="btn" type="submit">Оставить заявку</button>
          <p class="privacy-agree">
            Отправляя заявку, вы соглашаетесь с
            <a href="#privacy" data-privacy>политикой конфиденциальности</a>.
          </p>
          <p class="form-note js-ready-note" <?= $sent ? '' : 'hidden' ?>><?= $sent ? 'Скоро мы с вами свяжемся' : '' ?></p>
        </form>
    <?php
}

function quantlab_render_ready_page(string $slug): void
{
    $row = quantlab_ready_load($slug);
    $isAdmin = function_exists('quantlab_admin_logged_in') && quantlab_admin_logged_in();
    if (!$row || (($row['status'] ?? '') !== 'visible' && !$isAdmin)) {
        http_response_code(404);
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . '404.php';
        exit;
    }

    $canonical = quantlab_enforce_canonical('robots/' . $slug);
    $title = quantlab_ready_seo_title($row);
    $description = quantlab_ready_seo_description($row);
    $url = quantlab_ready_url($slug);
    $keywords = trim((string) ($row['keywords'] ?? ''));
    $image = trim((string) ($row['image'] ?? ''));
    $venues = quantlab_ready_venues();
    $venueLabel = $venues[$row['venue']] ?? $row['venue'];
    $sent = (string) ($_GET['sent'] ?? '') === '1';
    $priceNum = quantlab_ready_price_number((string) $row['price']);
    $related = array_values(array_filter(quantlab_ready_visible(), static function ($item) use ($slug) {
        return $item['slug'] !== $slug;
    }));
    $related = array_slice($related, 0, 3);

    $product = [
        '@type' => 'SoftwareApplication',
        'name' => $row['title'],
        'description' => $description,
        'inLanguage' => 'ru-RU',
        'applicationCategory' => 'FinanceApplication',
        'operatingSystem' => 'API',
        'url' => $canonical,
        'brand' => ['@id' => quantlab_org_id()],
        'offers' => [
            '@type' => 'Offer',
            'url' => $canonical,
            'priceCurrency' => 'RUB',
            'availability' => 'https://schema.org/InStock',
            'price' => $priceNum ?: '0',
        ],
    ];
    if ($image !== '') {
        $product['image'] = [quantlab_abs_url($image)];
    }
    if ($keywords !== '') {
        $product['keywords'] = $keywords;
    }
    $extra = quantlab_json_ld([
        '@context' => 'https://schema.org',
        '@graph' => [quantlab_organization_schema(), $product],
    ]);

    quantlab_render_start([
        'title' => $title,
        'description' => $description,
        'keywords' => $keywords,
        'image' => $image,
        'canonical' => $canonical,
        'og_type' => 'product',
        'modified_at' => (string) ($row['updated_at'] ?? ''),
        'active' => 'robots',
        'body_class' => 'page-inner page-ready',
        'robots' => ($row['status'] ?? '') === 'visible'
            ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : 'noindex,nofollow',
        'extra_head' => $extra,
    ]);
    ?>
      <article class="container ready-page">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Роботы', 'path' => '/robots/'],
            ['name' => $row['title'], 'path' => $url],
        ]) ?>
        <div class="ready-page-grid">
          <div>
            <p class="eyebrow">Готовый робот · <?= quantlab_h($venueLabel) ?></p>
            <h1><?= quantlab_h($row['title']) ?></h1>
            <?php if (($row['status'] ?? '') !== 'visible'): ?>
              <p class="article-meta"><span class="badge badge-warn">Скрыт</span></p>
            <?php endif; ?>
            <p class="ready-price ready-page-price"><?= quantlab_h(quantlab_ready_price_label((string) $row['price'])) ?></p>
            <?php if ($image !== ''): ?>
              <figure class="ready-page-cover">
                <img src="<?= quantlab_h($image) ?>" alt="<?= quantlab_h($row['title']) ?>" loading="eager" />
              </figure>
            <?php endif; ?>
            <div class="prose ready-page-body">
              <?= quantlab_ready_description_html((string) $row['description']) ?>
            </div>
            <?php if ($related): ?>
              <aside class="related">
                <h2>Другие роботы</h2>
                <ul>
                  <?php foreach ($related as $item): ?>
                    <li>
                      <a href="<?= quantlab_h(quantlab_ready_url($item['slug'])) ?>">
                        <?= quantlab_h($item['title']) ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </aside>
            <?php endif; ?>
          </div>
          <?php quantlab_render_ready_lead_form($row, $sent); ?>
        </div>
      </article>
      <script>
        (function () {
          var form = document.querySelector(".js-ready-form");
          if (!form) return;
          var note = form.querySelector(".js-ready-note");
          form.addEventListener("submit", function (event) {
            event.preventDefault();
            var data = new FormData(form);
            if ((data.get("website") || "").toString().trim()) return;
            var button = form.querySelector('button[type="submit"]');
            if (note) { note.hidden = false; note.textContent = "Отправляем заявку…"; }
            if (button) button.disabled = true;
            fetch("/api/lead.php", {
              method: "POST",
              headers: { Accept: "application/json", "X-Requested-With": "fetch" },
              body: data
            }).then(function (res) { return res.json().then(function (json) { return { res: res, json: json }; }); })
              .then(function (out) {
                if (!out.res.ok || out.json.ok === false) {
                  throw new Error(out.json.error || out.json.message || "Не удалось отправить");
                }
                if (note) note.textContent = "Заявка ушла на почту. Скоро свяжемся.";
                form.reset();
              })
              .catch(function (err) {
                if (note) note.textContent = (err && err.message) || "Не удалось отправить. Попробуйте ещё раз.";
              })
              .then(function () { if (button) button.disabled = false; });
          });
        })();
      </script>
    <?php
    quantlab_render_end();
}

