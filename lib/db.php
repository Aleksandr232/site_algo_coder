<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_db_enabled(): bool
{
    return quantlab_env('MYSQL_DATABASE') !== '';
}

function quantlab_db_last_error(?string $set = null): string
{
    static $error = '';
    if ($set !== null) {
        $error = $set;
    }
    return $error;
}

function quantlab_db_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Некорректное имя базы MySQL');
    }
    return $name;
}

function quantlab_db_connect(string $dsn, string $user, string $pass): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2,
    ];
    if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
        $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 2;
    }
    return new PDO($dsn, $user, $pass, $opts);
}

function quantlab_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    if (!quantlab_db_enabled()) {
        $pdo = null;
        return null;
    }
    $host = quantlab_env('MYSQL_HOST', 'localhost');
    $port = quantlab_env('MYSQL_PORT', '3306');
    $user = quantlab_env('MYSQL_USER');
    $pass = quantlab_env('MYSQL_PASSWORD');
    try {
        $name = quantlab_db_ident(quantlab_env('MYSQL_DATABASE'));
        $full = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        try {
            $pdo = quantlab_db_connect($full, $user, $pass);
        } catch (Throwable $e) {
            $code = $e instanceof PDOException ? (int) $e->errorInfo[1] : 0;
            if ($code !== 1049) {
                throw $e;
            }
            $server = quantlab_db_connect(
                'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
                $user,
                $pass
            );
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $pdo = quantlab_db_connect($full, $user, $pass);
        }
        quantlab_db_migrate($pdo);
        quantlab_db_last_error('');
        quantlab_db_write_status(true, '', quantlab_db_tables($pdo));
        return $pdo;
    } catch (Throwable $e) {
        quantlab_db_last_error($e->getMessage());
        quantlab_db_write_status(false, $e->getMessage(), []);
        $pdo = null;
        return null;
    }
}

function quantlab_db_tables(PDO $pdo): array
{
    $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $names = [];
    foreach ($rows ?: [] as $row) {
        if (!empty($row[0])) {
            $names[] = (string) $row[0];
        }
    }
    return $names;
}

function quantlab_db_write_status(bool $ok, string $error, array $tables): void
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'db-status.json',
        json_encode([
            'ok' => $ok,
            'error' => $error,
            'tables' => $tables,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_db_status(): array
{
    $pdo = quantlab_db();
    if ($pdo) {
        $tables = quantlab_db_tables($pdo);
        return [
            'ok' => true,
            'error' => '',
            'tables' => $tables,
            'ready' => !array_diff(['posts', 'post_redirects', 'leads'], $tables),
        ];
    }
    return [
        'ok' => false,
        'error' => quantlab_db_last_error() ?: 'Нет подключения к MySQL',
        'tables' => [],
        'ready' => false,
    ];
}


function quantlab_db_migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(191) NOT NULL UNIQUE,
            title VARCHAR(500) NOT NULL,
            excerpt TEXT NULL,
            body MEDIUMTEXT NULL,
            image VARCHAR(500) NULL,
            keywords VARCHAR(1000) NULL,
            seo_title VARCHAR(500) NULL,
            seo_description VARCHAR(1000) NULL,
            status VARCHAR(16) NOT NULL DEFAULT "draft",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            published_at DATETIME NULL,
            KEY status_published (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS post_redirects (
            old_slug VARCHAR(191) NOT NULL PRIMARY KEY,
            new_slug VARCHAR(191) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS leads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact VARCHAR(255) NOT NULL,
            market VARCHAR(64) NOT NULL,
            message TEXT NOT NULL,
            ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS strategies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(191) NOT NULL UNIQUE,
            venue VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT "visible",
            sort_order INT NOT NULL DEFAULT 0,
            dot VARCHAR(120) NOT NULL,
            eyebrow VARCHAR(255) NULL,
            title VARCHAR(500) NOT NULL,
            lead TEXT NULL,
            notes TEXT NULL,
            entry VARCHAR(32) NULL,
            stop VARCHAR(32) NULL,
            target VARCHAR(32) NULL,
            comon_id VARCHAR(32) NULL,
            instrument VARCHAR(64) NULL,
            source_url VARCHAR(500) NULL,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY status_sort (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function quantlab_dt_iso(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('c', $ts) : $value;
}

function quantlab_dt_sql(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function quantlab_post_from_row(array $row): array
{
    return [
        'slug' => $row['slug'] ?? '',
        'title' => $row['title'] ?? '',
        'excerpt' => $row['excerpt'] ?? '',
        'body' => $row['body'] ?? '',
        'image' => $row['image'] ?? '',
        'keywords' => $row['keywords'] ?? '',
        'seo_title' => $row['seo_title'] ?? '',
        'seo_description' => $row['seo_description'] ?? '',
        'status' => $row['status'] ?? 'draft',
        'created_at' => quantlab_dt_iso($row['created_at'] ?? null),
        'updated_at' => quantlab_dt_iso($row['updated_at'] ?? null),
        'published_at' => quantlab_dt_iso($row['published_at'] ?? null),
    ];
}

function quantlab_db_import_json_posts(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'posts';
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $post = json_decode((string) file_get_contents($file), true);
        if (!is_array($post) || empty($post['slug'])) {
            continue;
        }
        $st = $pdo->prepare(
            'INSERT INTO posts (slug, title, excerpt, body, image, keywords, seo_title, seo_description, status, created_at, updated_at, published_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $post['slug'],
            $post['title'] ?? '',
            $post['excerpt'] ?? '',
            $post['body'] ?? '',
            $post['image'] ?? '',
            $post['keywords'] ?? '',
            $post['seo_title'] ?? '',
            $post['seo_description'] ?? '',
            $post['status'] ?? 'draft',
            quantlab_dt_sql($post['created_at'] ?? null) ?: date('Y-m-d H:i:s'),
            quantlab_dt_sql($post['updated_at'] ?? null) ?: date('Y-m-d H:i:s'),
            quantlab_dt_sql($post['published_at'] ?? null),
        ]);
        if (function_exists('quantlab_blog_write_public_stub')) {
            quantlab_blog_write_public_stub($post['slug']);
        }
    }
}

function quantlab_db_import_json_redirects(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM post_redirects')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'redirects.json';
    if (!is_file($path)) {
        return;
    }
    $map = json_decode((string) file_get_contents($path), true);
    if (!is_array($map)) {
        return;
    }
    $st = $pdo->prepare('INSERT IGNORE INTO post_redirects (old_slug, new_slug) VALUES (?, ?)');
    foreach ($map as $from => $to) {
        if (!is_string($from) || !is_string($to) || $from === '' || $to === '') {
            continue;
        }
        $st->execute([$from, $to]);
    }
}
