<?php

function quantlab_strategies_path(): string
{
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'strategies';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'list.json';
}

function quantlab_strategy_venues(): array
{
    return [
        'comon' => 'Comon',
        'bybit' => 'Bybit',
    ];
}

function quantlab_strategy_defaults(): array
{
    $now = date('c');
    return [
        [
            'slug' => 'comon-131208',
            'venue' => 'comon',
            'status' => 'visible',
            'sort_order' => 10,
            'dot' => 'Юань · Comon',
            'eyebrow' => 'Кейс · автообновление с Comon',
            'title' => 'Юань Тренд 2-5-15',
            'lead' => 'Автоматическая стратегия по фьючерсу на юань. Работает в сторону устойчивого движения, характер умеренно-агрессивный.',
            'notes' => "Если сделка старше 5 дней и прибыль 7–10%, фиксация может быть досрочной.\nПри прибыли выше ~10% позиция обычно держится до цели 15%.\nС 01.06.2026 усилена логика тренда: меньше ложных входов в боковике.",
            'entry' => '2%',
            'stop' => '−5%',
            'target' => '+15%',
            'comon_id' => '131208',
            'instrument' => 'CNYRUB',
            'source_url' => 'https://www.comon.ru/strategies/131208/',
            'is_test' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'slug' => 'bybit-btc',
            'venue' => 'bybit',
            'status' => 'visible',
            'sort_order' => 20,
            'dot' => 'BTC · тест',
            'eyebrow' => 'Тестовый кейс · пока считаем доходность',
            'title' => 'BTC Trend · Bybit · тест',
            'lead' => 'Трендовый робот по бессрочному фьючерсу BTCUSDT на Bybit. Входит по направлению движения, режет риск и забирает профит по правилам системы.',
            'notes' => "Доходность считается по изменению баланса за каждый день, не по витрине.\nВ кривую входят закрытый результат и актуальная оценка счёта.\nСервер каждый день пишет снимок эквити и подтягивает историю сделок BTC.",
            'entry' => '',
            'stop' => '',
            'target' => '',
            'comon_id' => '',
            'instrument' => 'BTCUSDT',
            'source_url' => 'https://www.bybit.com/',
            'is_test' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ];
}

function quantlab_strategy_normalize(array $row): array
{
    $venue = (string) ($row['venue'] ?? 'comon');
    if (!isset(quantlab_strategy_venues()[$venue])) {
        $venue = 'comon';
    }
    $status = (($row['status'] ?? '') === 'hidden') ? 'hidden' : 'visible';
    $notes = (string) ($row['notes'] ?? '');
    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'venue' => $venue,
        'status' => $status,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'dot' => (string) ($row['dot'] ?? ''),
        'eyebrow' => (string) ($row['eyebrow'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'lead' => (string) ($row['lead'] ?? ''),
        'notes' => $notes,
        'entry' => (string) ($row['entry'] ?? ''),
        'stop' => (string) ($row['stop'] ?? ''),
        'target' => (string) ($row['target'] ?? ''),
        'comon_id' => preg_replace('/\D+/', '', (string) ($row['comon_id'] ?? '')),
        'instrument' => (string) ($row['instrument'] ?? ''),
        'source_url' => (string) ($row['source_url'] ?? ''),
        'is_test' => !empty($row['is_test']) ? 1 : 0,
        'created_at' => $row['created_at'] ?? date('c'),
        'updated_at' => $row['updated_at'] ?? date('c'),
    ];
}

function quantlab_strategy_note_lines(array $row): array
{
    $lines = preg_split("/\r\n|\n|\r/", (string) ($row['notes'] ?? '')) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static function ($line) {
        return $line !== '';
    }));
}

function quantlab_strategies_sort(array $items): array
{
    usort($items, static function ($a, $b) {
        $d = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        if ($d !== 0) {
            return $d;
        }
        return strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? ''));
    });
    return $items;
}

function quantlab_strategies_read_file(): array
{
    $path = quantlab_strategies_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        return [];
    }
    $items = [];
    foreach ($raw as $row) {
        if (is_array($row) && !empty($row['slug'])) {
            $items[] = quantlab_strategy_normalize($row);
        }
    }
    return quantlab_strategies_sort($items);
}

function quantlab_strategies_write_file(array $items): void
{
    quantlab_blog_write_json(quantlab_strategies_path(), array_values($items));
}

function quantlab_strategies_seed_rows(): array
{
    $fromFile = is_file(quantlab_strategies_path()) ? quantlab_strategies_read_file() : [];
    return $fromFile ?: quantlab_strategy_defaults();
}

function quantlab_strategies_seed_if_empty(): void
{
    $rows = quantlab_strategies_seed_rows();
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $check = $pdo->prepare('SELECT 1 FROM strategies WHERE slug = ? LIMIT 1');
        foreach ($rows as $row) {
            $row = quantlab_strategy_normalize($row);
            if ($row['slug'] === '') {
                continue;
            }
            $check->execute([$row['slug']]);
            if ($check->fetchColumn()) {
                continue;
            }
            $row['created_at'] = $row['created_at'] ?? date('c');
            $row['updated_at'] = date('c');
            quantlab_strategy_insert_row($pdo, $row);
        }
        return;
    }
    if (!is_file(quantlab_strategies_path())) {
        quantlab_strategies_write_file($rows);
    }
}

function quantlab_strategy_insert_row(PDO $pdo, array $row): void
{
    $st = $pdo->prepare(
        'INSERT INTO strategies (slug, venue, status, sort_order, dot, eyebrow, title, lead, notes, entry, stop, target, comon_id, instrument, source_url, is_test, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            venue=VALUES(venue), status=VALUES(status), sort_order=VALUES(sort_order), dot=VALUES(dot),
            eyebrow=VALUES(eyebrow), title=VALUES(title), lead=VALUES(lead), notes=VALUES(notes),
            entry=VALUES(entry), stop=VALUES(stop), target=VALUES(target), comon_id=VALUES(comon_id),
            instrument=VALUES(instrument), source_url=VALUES(source_url), is_test=VALUES(is_test),
            updated_at=VALUES(updated_at)'
    );
    $st->execute([
        $row['slug'],
        $row['venue'],
        $row['status'],
        $row['sort_order'],
        $row['dot'],
        $row['eyebrow'],
        $row['title'],
        $row['lead'],
        $row['notes'],
        $row['entry'],
        $row['stop'],
        $row['target'],
        $row['comon_id'],
        $row['instrument'],
        $row['source_url'],
        $row['is_test'],
        quantlab_dt_sql($row['created_at']) ?: date('Y-m-d H:i:s'),
        quantlab_dt_sql($row['updated_at']) ?: date('Y-m-d H:i:s'),
    ]);
}

function quantlab_strategies_all(): array
{
    quantlab_strategies_seed_if_empty();
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $rows = $pdo->query('SELECT * FROM strategies ORDER BY sort_order ASC, slug ASC')->fetchAll();
        return array_map('quantlab_strategy_normalize', $rows ?: []);
    }
    return quantlab_strategies_read_file();
}

function quantlab_strategies_visible(): array
{
    return array_values(array_filter(quantlab_strategies_all(), static function ($row) {
        return ($row['status'] ?? '') === 'visible';
    }));
}

function quantlab_strategy_load(string $slug): ?array
{
    foreach (quantlab_strategies_all() as $row) {
        if ($row['slug'] === $slug) {
            return $row;
        }
    }
    return null;
}

function quantlab_strategy_unique_slug(string $slug, ?string $ignore = null): string
{
    $base = quantlab_slugify($slug);
    if ($base === '') {
        $base = 'strategy';
    }
    $try = $base;
    $n = 2;
    while (true) {
        $taken = quantlab_strategy_load($try);
        if (!$taken || $try === $ignore) {
            return $try;
        }
        $try = $base . '-' . $n;
        $n++;
    }
}

function quantlab_strategy_save(array $input, ?string $currentSlug = null): array
{
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Укажите название');
    }
    $venue = (string) ($input['venue'] ?? 'comon');
    if (!isset(quantlab_strategy_venues()[$venue])) {
        throw new InvalidArgumentException('Площадка только Comon или Bybit');
    }
    $comonId = preg_replace('/\D+/', '', (string) ($input['comon_id'] ?? ''));
    if ($venue === 'comon' && $comonId === '') {
        throw new InvalidArgumentException('Для Comon нужен ID стратегии, например 131208');
    }

    $slugSource = trim((string) ($input['slug'] ?? ''));
    if ($slugSource === '') {
        $slugSource = $venue === 'comon' ? ('comon-' . $comonId) : $title;
    }
    $slug = quantlab_strategy_unique_slug($slugSource, $currentSlug);
    if (!quantlab_is_slug($slug)) {
        throw new InvalidArgumentException('Слаг только латиница, цифры и дефис');
    }

    $existing = $currentSlug ? quantlab_strategy_load($currentSlug) : null;
    $now = date('c');
    $row = quantlab_strategy_normalize([
        'slug' => $slug,
        'venue' => $venue,
        'status' => !empty($input['visible']) ? 'visible' : 'hidden',
        'sort_order' => (int) ($input['sort_order'] ?? ($existing['sort_order'] ?? time())),
        'dot' => trim((string) ($input['dot'] ?? '')) ?: $title,
        'eyebrow' => trim((string) ($input['eyebrow'] ?? '')),
        'title' => $title,
        'lead' => trim((string) ($input['lead'] ?? '')),
        'notes' => (string) ($input['notes'] ?? ''),
        'entry' => trim((string) ($input['entry'] ?? '')),
        'stop' => trim((string) ($input['stop'] ?? '')),
        'target' => trim((string) ($input['target'] ?? '')),
        'comon_id' => $comonId,
        'instrument' => trim((string) ($input['instrument'] ?? '')),
        'source_url' => trim((string) ($input['source_url'] ?? '')),
        'is_test' => !empty($input['is_test']) ? 1 : 0,
        'created_at' => $existing['created_at'] ?? $now,
        'updated_at' => $now,
    ]);

    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        if ($currentSlug && $currentSlug !== $slug) {
            $pdo->prepare('DELETE FROM strategies WHERE slug = ?')->execute([$currentSlug]);
        }
        quantlab_strategy_insert_row($pdo, $row);
    } else {
        $items = quantlab_strategies_read_file();
        $items = array_values(array_filter($items, static function ($item) use ($currentSlug, $slug) {
            return $item['slug'] !== $currentSlug && $item['slug'] !== $slug;
        }));
        $items[] = $row;
        quantlab_strategies_write_file(quantlab_strategies_sort($items));
    }
    return $row;
}

function quantlab_strategy_delete(string $slug): void
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->prepare('DELETE FROM strategies WHERE slug = ?')->execute([$slug]);
        return;
    }
    $items = array_values(array_filter(quantlab_strategies_read_file(), static function ($item) use ($slug) {
        return $item['slug'] !== $slug;
    }));
    quantlab_strategies_write_file($items);
}

function quantlab_strategy_set_status(string $slug, string $status): void
{
    $row = quantlab_strategy_load($slug);
    if (!$row) {
        return;
    }
    $row['status'] = $status === 'hidden' ? 'hidden' : 'visible';
    $row['updated_at'] = date('c');
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->prepare('UPDATE strategies SET status = ?, updated_at = ? WHERE slug = ?')->execute([
            $row['status'],
            date('Y-m-d H:i:s'),
            $slug,
        ]);
        return;
    }
    $items = [];
    foreach (quantlab_strategies_read_file() as $item) {
        $items[] = $item['slug'] === $slug ? $row : $item;
    }
    quantlab_strategies_write_file($items);
}

function quantlab_strategy_move(string $slug, string $dir): void
{
    $items = quantlab_strategies_all();
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
        $items[$index]['sort_order'] = $index * 10;
        $items[$swap]['sort_order'] = $swap * 10;
    }
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('UPDATE strategies SET sort_order = ?, updated_at = ? WHERE slug = ?');
        $now = date('Y-m-d H:i:s');
        $st->execute([$items[$index]['sort_order'], $now, $items[$index]['slug']]);
        $st->execute([$items[$swap]['sort_order'], $now, $items[$swap]['slug']]);
        return;
    }
    quantlab_strategies_write_file($items);
}

function quantlab_first_visible_comon(): ?array
{
    foreach (quantlab_strategies_visible() as $row) {
        if ($row['venue'] === 'comon') {
            return $row;
        }
    }
    return null;
}

function quantlab_render_case_slide(array $row, bool $hero = false): void
{
    $slug = (string) $row['slug'];
    $venue = (string) $row['venue'];
    $notes = quantlab_strategy_note_lines($row);
    $url = (string) ($row['source_url'] ?? '');
    $host = $url !== '' ? (preg_replace('#^https?://(www\.)?#', '', $url) ?: $url) : '';
    $host = rtrim((string) $host, '/');
    if ($venue === 'bybit') {
        quantlab_render_bybit_slide($row, $notes, $url, $host);
        return;
    }
    quantlab_render_comon_slide($row, $notes, $url, $host, $hero);
}

function quantlab_render_comon_slide(array $row, array $notes, string $url, string $host, bool $hero): void
{
    $id = quantlab_h($row['slug']);
    ?>
              <article class="case-slide" id="slide-<?= $id ?>" data-venue="comon" data-slug="<?= $id ?>" data-comon-id="<?= quantlab_h($row['comon_id']) ?>"<?= $hero ? ' data-hero="1"' : '' ?>>
          <div class="section-head case-head">
            <div>
              <p class="eyebrow"><?= quantlab_h($row['eyebrow'] ?: 'Кейс · Comon') ?></p>
              <h2 class="js-title"><?= quantlab_h($row['title']) ?></h2>
              <p class="case-meta">
                Запуск
                <span class="js-start">—</span> · источник
                <?php if ($url !== ''): ?>
                  <a class="js-link" href="<?= quantlab_h($url) ?>" target="_blank" rel="noopener"><?= quantlab_h($host) ?></a>
                <?php else: ?>
                  <a class="js-link" href="https://www.comon.ru/strategies/<?= quantlab_h($row['comon_id']) ?>/" target="_blank" rel="noopener">comon.ru</a>
                <?php endif; ?>
              </p>
            </div>
            <button class="parsed-stamp js-stamp" type="button" title="Обновить с Comon">Обновить с Comon</button>
          </div>
          <div class="metrics js-metrics"></div>
          <div class="chart-wrap glass">
            <div class="chart-toolbar">
              <div>
                <h3>Кривая доходности</h3>
                <p>Накопленный результат публичной стратегии, %</p>
              </div>
              <div class="pills js-pills" role="tablist" aria-label="Период графика">
                <button type="button" class="pill is-active" data-range="all">Всё время</button>
                <button type="button" class="pill" data-range="90">90 дней</button>
                <button type="button" class="pill" data-range="30">30 дней</button>
              </div>
            </div>
            <div class="chart-stage">
              <canvas class="js-chart"></canvas>
              <div class="chart-tip js-tip" hidden></div>
            </div>
          </div>
          <div class="case-grid">
            <article class="glass pad">
              <h3>Логика робота</h3>
              <?php if ($row['lead'] !== ''): ?><p><?= quantlab_h($row['lead']) ?></p><?php endif; ?>
              <?php if ($row['entry'] !== '' || $row['stop'] !== '' || $row['target'] !== ''): ?>
              <div class="rule-row">
                <div><span>Вход</span><strong><?= quantlab_h($row['entry'] ?: '—') ?></strong><em>от депозита</em></div>
                <div><span>Стоп</span><strong class="neg"><?= quantlab_h($row['stop'] ?: '—') ?></strong><em>от депозита</em></div>
                <div><span>Цель</span><strong class="pos"><?= quantlab_h($row['target'] ?: '—') ?></strong><em>от депозита</em></div>
              </div>
              <?php endif; ?>
              <?php if ($notes): ?>
              <ul class="fine-list">
                <?php foreach ($notes as $line): ?><li><?= quantlab_h($line) ?></li><?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </article>
            <article class="glass pad">
              <h3>Состав и доступ</h3>
              <div class="bars js-bars"></div>
              <dl class="spec">
                <div><dt>Профиль риска</dt><dd class="js-risk">—</dd></div>
                <div><dt>Категория</dt><dd class="js-cat">—</dd></div>
                <div><dt>Тариф</dt><dd class="js-tariff">—</dd></div>
                <div><dt>Лимит стратегии</dt><dd class="js-limit">—</dd></div>
                <div><dt>ИТА</dt><dd class="js-ita">—</dd></div>
                <div><dt>Позиция сейчас</dt><dd class="js-position">—</dd></div>
              </dl>
            </article>
          </div>
              </article>
    <?php
}

function quantlab_render_bybit_slide(array $row, array $notes, string $url, string $host): void
{
    $id = quantlab_h($row['slug']);
    $instrument = $row['instrument'] !== '' ? $row['instrument'] : 'BTCUSDT';
    ?>
              <article class="case-slide" id="slide-<?= $id ?>" data-venue="bybit" data-slug="<?= $id ?>" data-instrument="<?= quantlab_h($instrument) ?>">
          <div class="section-head case-head">
            <div>
              <p class="eyebrow"><?= quantlab_h($row['eyebrow'] ?: 'Кейс · Bybit') ?></p>
              <h2 class="js-title"><?= quantlab_h($row['title']) ?></h2>
              <p class="case-meta">
                <?= !empty($row['is_test']) ? 'Тестовый контур' : 'Боевой контур' ?>
                <?= quantlab_h($instrument) ?>
                <?php if ($url !== ''): ?>
                  · <a href="<?= quantlab_h($url) ?>" target="_blank" rel="noopener"><?= quantlab_h($host) ?></a>
                <?php endif; ?>
              </p>
            </div>
            <button class="parsed-stamp js-stamp" type="button" title="Обновить с Bybit">Обновить с Bybit</button>
          </div>
          <div class="metrics js-metrics"></div>
          <div class="chart-wrap glass">
            <div class="chart-toolbar">
              <div>
                <h3>Кривая баланса</h3>
                <p>Дневная доходность счёта Unified, %</p>
              </div>
              <div class="pills js-pills" role="tablist" aria-label="Период графика Bybit">
                <button type="button" class="pill is-active" data-range="all">Всё время</button>
                <button type="button" class="pill" data-range="90">90 дней</button>
                <button type="button" class="pill" data-range="30">30 дней</button>
              </div>
            </div>
            <div class="chart-stage">
              <canvas class="js-chart"></canvas>
              <div class="chart-tip js-tip" hidden></div>
            </div>
          </div>
          <div class="case-grid">
            <article class="glass pad">
              <h3>Логика робота</h3>
              <?php if ($row['lead'] !== ''): ?><p><?= quantlab_h($row['lead']) ?></p><?php endif; ?>
              <div class="rule-row">
                <div><span>Площадка</span><strong>Bybit</strong><em>Unified API</em></div>
                <div><span>Инструмент</span><strong><?= quantlab_h($instrument) ?></strong><em>Perp</em></div>
                <div><span>Счёт</span><strong class="js-equity">—</strong><em>текущий баланс</em></div>
              </div>
              <?php if ($notes): ?>
              <ul class="fine-list">
                <?php foreach ($notes as $line): ?><li><?= quantlab_h($line) ?></li><?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </article>
            <article class="glass pad">
              <h3>Счёт и позиция</h3>
              <div class="bars js-bars"></div>
              <dl class="spec">
                <div><dt>Площадка</dt><dd>Bybit Unified</dd></div>
                <div><dt>Инструмент</dt><dd><?= quantlab_h($instrument) ?></dd></div>
                <div><dt>Позиция</dt><dd class="js-position">—</dd></div>
                <div><dt>Средняя</dt><dd class="js-avg">—</dd></div>
                <div><dt>Нереализ. PnL</dt><dd class="js-upl">—</dd></div>
                <div><dt>Доступно</dt><dd class="js-free">—</dd></div>
              </dl>
            </article>
          </div>
              </article>
    <?php
}
