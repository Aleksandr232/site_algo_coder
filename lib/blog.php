<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'cache.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'site.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

function quantlab_blog_root(): string
{
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'blog';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $posts = $dir . DIRECTORY_SEPARATOR . 'posts';
    if (!is_dir($posts)) {
        mkdir($posts, 0775, true);
    }
    return $dir;
}

function quantlab_blog_posts_dir(): string
{
    return quantlab_blog_root() . DIRECTORY_SEPARATOR . 'posts';
}

function quantlab_blog_index_path(): string
{
    return quantlab_blog_root() . DIRECTORY_SEPARATOR . 'index.json';
}

function quantlab_blog_redirects_path(): string
{
    return quantlab_blog_root() . DIRECTORY_SEPARATOR . 'redirects.json';
}

function quantlab_clip(string $text, int $len): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $len, 'UTF-8');
    }
    return preg_match('/^.{0,' . $len . '}/us', $text, $match) ? $match[0] : substr($text, 0, $len);
}

function quantlab_slugify(string $text): string
{
    $text = trim($text);
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e',
        'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'j', 'К' => 'k', 'Л' => 'l', 'М' => 'm',
        'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch',
        'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
        'І' => 'i', 'Ї' => 'yi', 'Є' => 'ye', 'Ґ' => 'g',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    $text = preg_replace('/-+/', '-', $text);
    if ($text === '' || in_array($text, ['index', 'admin', 'view'], true)) {
        $text = 'statya-' . date('Ymd-His');
    }
    return $text;
}

function quantlab_is_slug(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function quantlab_blog_read_json(string $path, $default)
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : $default;
}

function quantlab_blog_write_json(string $path, $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_blog_post_path(string $slug): string
{
    return quantlab_blog_posts_dir() . DIRECTORY_SEPARATOR . $slug . '.json';
}

function quantlab_blog_load(string $slug): ?array
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('SELECT * FROM posts WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ? quantlab_post_from_row($row) : null;
    }
    $path = quantlab_blog_post_path($slug);
    if (!is_file($path)) {
        return null;
    }
    $post = quantlab_blog_read_json($path, null);
    return is_array($post) ? $post : null;
}

function quantlab_blog_redirect_target(string $slug): ?string
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('SELECT new_slug FROM post_redirects WHERE old_slug = ? LIMIT 1');
        $st->execute([$slug]);
        $target = $st->fetchColumn();
        return is_string($target) && $target !== '' ? $target : null;
    }
    $map = quantlab_blog_read_json(quantlab_blog_redirects_path(), []);
    $target = $map[$slug] ?? null;
    return is_string($target) && $target !== '' ? $target : null;
}

function quantlab_blog_set_redirect(string $from, string $to): void
{
    if ($from === $to || !quantlab_is_slug($from) || !quantlab_is_slug($to)) {
        return;
    }
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->prepare('UPDATE post_redirects SET new_slug = ? WHERE new_slug = ?')->execute([$to, $from]);
        $pdo->prepare('INSERT INTO post_redirects (old_slug, new_slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE new_slug = VALUES(new_slug)')
            ->execute([$from, $to]);
        $pdo->prepare('DELETE FROM post_redirects WHERE old_slug = ?')->execute([$to]);
        return;
    }
    $map = quantlab_blog_read_json(quantlab_blog_redirects_path(), []);
    foreach ($map as $old => $target) {
        if ($target === $from) {
            $map[$old] = $to;
        }
    }
    $map[$from] = $to;
    unset($map[$to]);
    quantlab_blog_write_json(quantlab_blog_redirects_path(), $map);
}

function quantlab_blog_rebuild_index(): array
{
    $items = [];
    foreach (glob(quantlab_blog_posts_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $post = quantlab_blog_read_json($file, null);
        if (!is_array($post) || empty($post['slug'])) {
            continue;
        }
        $items[] = quantlab_blog_index_item($post);
    }
    usort($items, static function ($a, $b) {
        return strcmp((string) ($b['published_at'] ?? $b['updated_at'] ?? ''), (string) ($a['published_at'] ?? $a['updated_at'] ?? ''));
    });
    quantlab_blog_write_json(quantlab_blog_index_path(), $items);
    return $items;
}

function quantlab_blog_index_item(array $post): array
{
    return [
        'slug' => $post['slug'],
        'title' => $post['title'] ?? '',
        'excerpt' => $post['excerpt'] ?? '',
        'image' => $post['image'] ?? '',
        'keywords' => $post['keywords'] ?? '',
        'status' => $post['status'] ?? 'draft',
        'published_at' => $post['published_at'] ?? null,
        'updated_at' => $post['updated_at'] ?? null,
    ];
}

function quantlab_blog_uploads_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blog';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function quantlab_blog_normalize_keywords(string $raw): string
{
    $parts = preg_split('/[,;\n]+/', $raw) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && !in_array($part, $clean, true)) {
            $clean[] = $part;
        }
    }
    return implode(', ', $clean);
}

function quantlab_blog_delete_image(?string $path): void
{
    if (!$path) {
        return;
    }
    if (!preg_match('#^/uploads/blog/([a-zA-Z0-9._-]+)$#', $path, $match)) {
        return;
    }
    $file = quantlab_blog_uploads_dir() . DIRECTORY_SEPARATOR . $match[1];
    if (is_file($file)) {
        unlink($file);
    }
}

function quantlab_blog_handle_image(?string $current, array $file, bool $remove): ?string
{
    if ($remove && $current) {
        quantlab_blog_delete_image($current);
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
    $dest = quantlab_blog_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new InvalidArgumentException('Не удалось сохранить картинку');
    }
    if ($current) {
        quantlab_blog_delete_image($current);
    }
    return '/uploads/blog/' . $name;
}

function quantlab_blog_all(): array
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $rows = $pdo->query(
            'SELECT slug, title, excerpt, image, keywords, status, published_at, updated_at
             FROM posts
             ORDER BY COALESCE(published_at, updated_at) DESC, id DESC'
        )->fetchAll();
        return array_map(static function ($row) {
            return quantlab_blog_index_item(quantlab_post_from_row($row));
        }, $rows ?: []);
    }
    $items = quantlab_blog_read_json(quantlab_blog_index_path(), null);
    if (!is_array($items)) {
        return quantlab_blog_rebuild_index();
    }
    return $items;
}

function quantlab_blog_published(): array
{
    return array_values(array_filter(quantlab_blog_all(), static function ($item) {
        return ($item['status'] ?? '') === 'published';
    }));
}

function quantlab_blog_unique_slug(string $slug, ?string $ignore = null): string
{
    $base = quantlab_slugify($slug);
    $try = $base;
    $n = 2;
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    while (true) {
        $taken = false;
        if ($pdo) {
            $st = $pdo->prepare('SELECT 1 FROM posts WHERE slug = ? LIMIT 1');
            $st->execute([$try]);
            $taken = (bool) $st->fetchColumn();
        } else {
            $taken = is_file(quantlab_blog_post_path($try));
        }
        if (!$taken || $try === $ignore) {
            return $try;
        }
        $try = $base . '-' . $n;
        $n++;
    }
}

function quantlab_blog_public_dir(string $slug): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . $slug;
}

function quantlab_blog_write_public_stub(string $slug): void
{
    $dir = quantlab_blog_public_dir($slug);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $php = "<?php\n"
        . "declare(strict_types=1);\n"
        . "\$quantlab_slug = " . var_export($slug, true) . ";\n"
        . "require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';\n"
        . "quantlab_render_public_article(\$quantlab_slug);\n";
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.php', $php, LOCK_EX);
}

function quantlab_blog_write_redirect_stub(string $from, string $to): void
{
    $dir = quantlab_blog_public_dir($from);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $url = quantlab_public_path('blog/' . $to);
    $php = "<?php\nheader('Location: ' . " . var_export($url, true) . ", true, 301);\nexit;\n";
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.php', $php, LOCK_EX);
}

function quantlab_blog_remove_public_dir(string $slug): void
{
    $dir = quantlab_blog_public_dir($slug);
    $file = $dir . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($file)) {
        unlink($file);
    }
    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function quantlab_blog_save(array $input, ?string $currentSlug = null): array
{
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Укажите заголовок');
    }
    $slugSource = trim((string) ($input['slug'] ?? ''));
    $slug = quantlab_blog_unique_slug($slugSource !== '' ? $slugSource : $title, $currentSlug);
    if (!quantlab_is_slug($slug)) {
        throw new InvalidArgumentException('Слаг может содержать только латиницу, цифры и дефис');
    }

    $now = date('c');
    $existing = $currentSlug ? quantlab_blog_load($currentSlug) : null;
    $status = (($input['status'] ?? '') === 'published') ? 'published' : 'draft';
    $publishedAt = $existing['published_at'] ?? null;
    if ($status === 'published' && !$publishedAt) {
        $publishedAt = $now;
    }
    if ($status === 'draft') {
        $publishedAt = $existing['published_at'] ?? null;
    }

    $image = $existing['image'] ?? null;
    if (array_key_exists('image', $input)) {
        $image = $input['image'] ?: null;
    }

    $post = [
        'slug' => $slug,
        'title' => $title,
        'excerpt' => trim((string) ($input['excerpt'] ?? '')),
        'body' => (string) ($input['body'] ?? ''),
        'image' => $image,
        'keywords' => quantlab_blog_normalize_keywords((string) ($input['keywords'] ?? '')),
        'seo_title' => $title,
        'seo_description' => trim((string) ($input['seo_description'] ?? '')),
        'status' => $status,
        'created_at' => $existing['created_at'] ?? $now,
        'updated_at' => $now,
        'published_at' => $publishedAt,
    ];

    if ($currentSlug && $currentSlug !== $slug) {
        $oldPath = quantlab_blog_post_path($currentSlug);
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
        quantlab_blog_set_redirect($currentSlug, $slug);
        quantlab_blog_write_redirect_stub($currentSlug, $slug);
    }

    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        if ($currentSlug && $currentSlug !== $slug) {
            $pdo->prepare('DELETE FROM posts WHERE slug = ?')->execute([$currentSlug]);
        }
        $st = $pdo->prepare(
            'INSERT INTO posts (slug, title, excerpt, body, image, keywords, seo_title, seo_description, status, created_at, updated_at, published_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                title=VALUES(title), excerpt=VALUES(excerpt), body=VALUES(body), image=VALUES(image),
                keywords=VALUES(keywords), seo_title=VALUES(seo_title), seo_description=VALUES(seo_description),
                status=VALUES(status), updated_at=VALUES(updated_at), published_at=VALUES(published_at)'
        );
        $st->execute([
            $post['slug'],
            $post['title'],
            $post['excerpt'],
            $post['body'],
            $post['image'],
            $post['keywords'],
            $post['seo_title'],
            $post['seo_description'],
            $post['status'],
            quantlab_dt_sql($post['created_at']) ?: date('Y-m-d H:i:s'),
            quantlab_dt_sql($post['updated_at']) ?: date('Y-m-d H:i:s'),
            quantlab_dt_sql($post['published_at']),
        ]);
    } else {
        quantlab_blog_write_json(quantlab_blog_post_path($slug), $post);
        quantlab_blog_rebuild_index();
    }
    quantlab_blog_write_public_stub($slug);
    quantlab_write_seo_files();
    if ($status === 'published') {
        quantlab_ping_search_engines();
    }
    return $post;
}

function quantlab_blog_delete(string $slug): void
{
    $post = quantlab_blog_load($slug);
    if ($post) {
        quantlab_blog_delete_image($post['image'] ?? null);
    }
    $path = quantlab_blog_post_path($slug);
    if (is_file($path)) {
        unlink($path);
    }
    quantlab_blog_remove_public_dir($slug);
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('SELECT old_slug FROM post_redirects WHERE new_slug = ? OR old_slug = ?');
        $st->execute([$slug, $slug]);
        foreach ($st->fetchAll() as $row) {
            if (($row['old_slug'] ?? '') !== $slug) {
                quantlab_blog_remove_public_dir((string) $row['old_slug']);
            }
        }
        $pdo->prepare('DELETE FROM posts WHERE slug = ?')->execute([$slug]);
        $pdo->prepare('DELETE FROM post_redirects WHERE new_slug = ? OR old_slug = ?')->execute([$slug, $slug]);
    } else {
        $map = quantlab_blog_read_json(quantlab_blog_redirects_path(), []);
        $changed = false;
        foreach ($map as $from => $to) {
            if ($to === $slug || $from === $slug) {
                unset($map[$from]);
                $changed = true;
                if ($from !== $slug) {
                    quantlab_blog_remove_public_dir((string) $from);
                }
            }
        }
        if ($changed) {
            quantlab_blog_write_json(quantlab_blog_redirects_path(), $map);
        }
        quantlab_blog_rebuild_index();
    }
    quantlab_write_seo_files();
}

function quantlab_markdown(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $blocks = preg_split('/\n{2,}/', trim($escaped));
    $html = [];
    foreach ($blocks ?: [] as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (preg_match('/^```(?:\w+)?\n?(.*?)```$/s', $block, $m)) {
            $html[] = '<pre><code>' . trim($m[1]) . '</code></pre>';
            continue;
        }
        if (preg_match('/^### (.+)$/u', $block, $m) && substr_count($block, "\n") === 0) {
            $html[] = '<h3>' . quantlab_inline_md($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^## (.+)$/u', $block, $m) && substr_count($block, "\n") === 0) {
            $html[] = '<h2>' . quantlab_inline_md($m[1]) . '</h2>';
            continue;
        }
        if (preg_match('/^# (.+)$/u', $block, $m) && substr_count($block, "\n") === 0) {
            $html[] = '<h2>' . quantlab_inline_md($m[1]) . '</h2>';
            continue;
        }
        $lines = explode("\n", $block);
        $isList = true;
        foreach ($lines as $line) {
            if (!preg_match('/^\s*[-*]\s+/', $line)) {
                $isList = false;
                break;
            }
        }
        if ($isList) {
            $items = array_map(static function ($line) {
                return '<li>' . quantlab_inline_md(preg_replace('/^\s*[-*]\s+/', '', $line)) . '</li>';
            }, $lines);
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }
        $html[] = '<p>' . quantlab_inline_md(str_replace("\n", "<br />\n", $block)) . '</p>';
    }
    return implode("\n", $html);
}

function quantlab_inline_md(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[a-z0-9][^)\s]*)\)/i',
        static function ($m) {
            return '<a href="' . $m[2] . '">' . $m[1] . '</a>';
        },
        $text
    );
    return $text;
}

function quantlab_post_seo_title(array $post): string
{
    $title = trim((string) ($post['title'] ?? ''));
    return $title !== '' ? $title : 'Статья';
}

function quantlab_post_seo_description(array $post): string
{
    $desc = trim((string) ($post['seo_description'] ?? ''));
    if ($desc !== '') {
        return $desc;
    }
    $excerpt = trim((string) ($post['excerpt'] ?? ''));
    if ($excerpt !== '') {
        return $excerpt;
    }
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags(quantlab_markdown((string) ($post['body'] ?? '')))));
    return $plain !== '' ? quantlab_clip($plain, 160) : 'Статья AM QuantLab о торговых роботах и финтехе.';
}

function quantlab_sitemap_xml(): string
{
    $today = gmdate('Y-m-d');
    $urls = [
        ['loc' => quantlab_abs_url('/'), 'lastmod' => $today, 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => quantlab_abs_url('/blog/'), 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '0.9'],
        ['loc' => quantlab_abs_url('/robots/'), 'lastmod' => $today, 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['loc' => quantlab_abs_url('rss.xml'), 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '0.4'],
        ['loc' => quantlab_abs_url('llms.txt'), 'lastmod' => $today, 'changefreq' => 'weekly', 'priority' => '0.3'],
    ];
    foreach (quantlab_blog_published() as $item) {
        $post = quantlab_blog_load($item['slug']);
        $last = $post['updated_at'] ?? $post['published_at'] ?? null;
        $urls[] = [
            'loc' => quantlab_abs_url('blog/' . $item['slug']),
            'lastmod' => $last ? substr((string) $last, 0, 10) : $today,
            'changefreq' => 'weekly',
            'priority' => '0.8',
            'image' => !empty($post['image']) ? quantlab_abs_url($post['image']) : null,
            'image_title' => $post['title'] ?? '',
        ];
    }
    if (function_exists('quantlab_ready_visible')) {
        foreach (quantlab_ready_visible() as $item) {
            $last = $item['updated_at'] ?? $item['created_at'] ?? null;
            $urls[] = [
                'loc' => quantlab_abs_url('robots/' . $item['slug']),
                'lastmod' => $last ? substr((string) $last, 0, 10) : $today,
                'changefreq' => 'weekly',
                'priority' => '0.85',
                'image' => !empty($item['image']) ? quantlab_abs_url($item['image']) : null,
                'image_title' => $item['title'] ?? '',
            ];
        }
    }

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . quantlab_h($url['loc']) . "</loc>\n";
        if (!empty($url['lastmod'])) {
            $xml .= '    <lastmod>' . quantlab_h($url['lastmod']) . "</lastmod>\n";
        }
        $xml .= '    <changefreq>' . quantlab_h($url['changefreq']) . "</changefreq>\n";
        $xml .= '    <priority>' . quantlab_h($url['priority']) . "</priority>\n";
        if (!empty($url['image'])) {
            $xml .= "    <image:image>\n";
            $xml .= '      <image:loc>' . quantlab_h($url['image']) . "</image:loc>\n";
            if (!empty($url['image_title'])) {
                $xml .= '      <image:title>' . quantlab_h($url['image_title']) . "</image:title>\n";
            }
            $xml .= "    </image:image>\n";
        }
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";
    return $xml;
}

function quantlab_rss_xml(): string
{
    $items = '';
    foreach (quantlab_blog_published() as $row) {
        $post = quantlab_blog_load($row['slug']);
        if (!$post) {
            continue;
        }
        $link = quantlab_abs_url('blog/' . $post['slug']);
        $date = strtotime((string) ($post['published_at'] ?? $post['updated_at'] ?? 'now')) ?: time();
        $desc = quantlab_h(quantlab_post_seo_description($post));
        $items .= "    <item>\n"
            . '      <title>' . quantlab_h($post['title']) . "</title>\n"
            . '      <link>' . quantlab_h($link) . "</link>\n"
            . '      <guid isPermaLink="true">' . quantlab_h($link) . "</guid>\n"
            . '      <pubDate>' . gmdate(DATE_RSS, $date) . "</pubDate>\n"
            . '      <description>' . $desc . "</description>\n"
            . "    </item>\n";
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
        . "  <channel>\n"
        . '    <title>Блог AM QuantLab</title>' . "\n"
        . '    <link>' . quantlab_h(quantlab_abs_url('/blog/')) . "</link>\n"
        . "    <description>Статьи AM QuantLab о торговых роботах, API и финтехе</description>\n"
        . "    <language>ru</language>\n"
        . '    <atom:link href="' . quantlab_h(quantlab_abs_url('rss.xml')) . '" rel="self" type="application/rss+xml" />' . "\n"
        . $items
        . "  </channel>\n"
        . "</rss>\n";
}

function quantlab_robots_txt(): string
{
    $host = parse_url(quantlab_site_url(), PHP_URL_HOST) ?: '';
    $deny = "Allow: /\n"
        . "Allow: /blog/\n"
        . "Allow: /robots/\n"
        . "Allow: /uploads/\n"
        . "Allow: /favicon.ico\n"
        . "Allow: /favicon.svg\n"
        . "Allow: /favicon-48.png\n"
        . "Allow: /favicon-96.png\n"
        . "Allow: /favicon-512.png\n"
        . "Allow: /apple-touch-icon.png\n"
        . "Allow: /llms.txt\n"
        . "Disallow: /admin/\n"
        . "Disallow: /lib/\n"
        . "Disallow: /api/\n"
        . "Disallow: /install.php\n"
        . "Disallow: /data/blog/\n"
        . "Disallow: /router.php\n";
    $googleAgents = [
        'Googlebot',
        'Googlebot-Image',
        'Google-Extended',
        'GoogleOther',
        'Google-CloudVertexBot',
        'Storebot-Google',
    ];
    $txt = "User-agent: *\n" . $deny . "\n";
    foreach ($googleAgents as $agent) {
        $txt .= 'User-agent: ' . $agent . "\n" . $deny . "\n";
    }
    $txt .= "User-agent: Yandex\n"
        . $deny
        . "Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&ysclid&gclid&fbclid\n"
        . ($host !== '' ? 'Host: ' . $host . "\n" : '')
        . "\n"
        . 'Sitemap: ' . quantlab_abs_url('sitemap.xml') . "\n";
    return $txt;
}

function quantlab_write_seo_files(): void
{
    $root = dirname(__DIR__);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'sitemap.xml', quantlab_sitemap_xml(), LOCK_EX);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'robots.txt', quantlab_robots_txt(), LOCK_EX);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'rss.xml', quantlab_rss_xml(), LOCK_EX);
    if (function_exists('quantlab_llms_txt')) {
        file_put_contents($root . DIRECTORY_SEPARATOR . 'llms.txt', quantlab_llms_txt(), LOCK_EX);
    }
}

function quantlab_ping_search_engines(): void
{
    $sitemap = quantlab_abs_url('sitemap.xml');
    foreach ([
        'https://webmaster.yandex.ru/ping?sitemap=' . rawurlencode($sitemap),
        'https://www.google.com/ping?sitemap=' . rawurlencode($sitemap),
    ] as $url) {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (Throwable $e) {
        }
    }
}

function quantlab_render_public_article(string $slug): void
{
    $target = quantlab_blog_redirect_target($slug);
    if ($target) {
        header('Location: ' . quantlab_public_path('blog/' . $target), true, 301);
        exit;
    }

    $post = quantlab_blog_load($slug);
    $isAdmin = function_exists('quantlab_admin_logged_in') && quantlab_admin_logged_in();
    if (!$post || (($post['status'] ?? '') !== 'published' && !$isAdmin)) {
        http_response_code(404);
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . '404.php';
        exit;
    }

    $canonical = quantlab_enforce_canonical('blog/' . $slug);
    $title = quantlab_post_seo_title($post);
    $description = quantlab_post_seo_description($post);
    $url = quantlab_public_path('blog/' . $slug);
    $published = $post['published_at'] ?: $post['created_at'];
    $related = array_values(array_filter(quantlab_blog_published(), static function ($item) use ($slug) {
        return $item['slug'] !== $slug;
    }));
    $related = array_slice($related, 0, 3);

    $keywords = trim((string) ($post['keywords'] ?? ''));
    $image = trim((string) ($post['image'] ?? ''));
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $description,
        'inLanguage' => 'ru-RU',
        'datePublished' => $published,
        'dateModified' => $post['updated_at'] ?? $published,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonical,
        ],
        'author' => ['@id' => quantlab_org_id()],
        'publisher' => ['@id' => quantlab_org_id()],
        'isAccessibleForFree' => true,
        'speakable' => [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => ['.article-page h1', '.article-page .lead', '.article-page .prose p'],
        ],
    ];
    if ($image !== '') {
        $schema['image'] = [quantlab_abs_url($image)];
    }
    if ($keywords !== '') {
        $schema['keywords'] = $keywords;
    }
    unset($schema['@context']);
    $extra = quantlab_json_ld([
        '@context' => 'https://schema.org',
        '@graph' => [quantlab_organization_schema(), $schema],
    ]);

    quantlab_render_start([
        'title' => $title,
        'description' => $description,
        'keywords' => $keywords,
        'image' => $image,
        'canonical' => $canonical,
        'og_type' => 'article',
        'published_at' => (string) $published,
        'modified_at' => (string) ($post['updated_at'] ?? $published),
        'active' => 'blog',
        'body_class' => 'page-inner page-article',
        'robots' => ($post['status'] ?? '') === 'published'
            ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : 'noindex,nofollow',
        'extra_head' => $extra,
    ]);
    ?>
      <article class="container article-page">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Блог', 'path' => '/blog/'],
            ['name' => $post['title'], 'path' => $url],
        ]) ?>
        <p class="eyebrow">Блог · <?= quantlab_h($slug) ?></p>
        <h1><?= quantlab_h($post['title']) ?></h1>
        <p class="article-meta">
          <?php if (($post['status'] ?? '') !== 'published'): ?>
            <span class="badge badge-warn">Черновик</span>
          <?php endif; ?>
          <time datetime="<?= quantlab_h(substr((string) $published, 0, 10)) ?>">
            <?= quantlab_h(date('d.m.Y', strtotime((string) $published) ?: time())) ?>
          </time>
        </p>
        <?php if ($post['excerpt']): ?>
          <p class="lead"><?= quantlab_h($post['excerpt']) ?></p>
        <?php endif; ?>
        <?php if ($image !== ''): ?>
          <figure class="article-cover">
            <img src="<?= quantlab_h($image) ?>" alt="<?= quantlab_h($post['title']) ?>" loading="eager" />
          </figure>
        <?php endif; ?>
        <div class="prose">
          <?= $post['body'] !== '' ? quantlab_markdown($post['body']) : '<p>Текст статьи скоро появится.</p>' ?>
        </div>
        <?php if ($related): ?>
          <aside class="related">
            <h2>Ещё из блога</h2>
            <ul>
              <?php foreach ($related as $item): ?>
                <li>
                  <a href="<?= quantlab_h(quantlab_public_path('blog/' . $item['slug'])) ?>">
                    <?= quantlab_h($item['title']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </aside>
        <?php endif; ?>
      </article>
    <?php
    quantlab_render_end();
}
