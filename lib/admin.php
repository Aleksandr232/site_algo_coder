<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_admin_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('quantlab_admin');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

function quantlab_admin_logged_in(): bool
{
    quantlab_admin_boot();
    return !empty($_SESSION['admin_ok']);
}

function quantlab_admin_require(): void
{
    if (!quantlab_admin_logged_in()) {
        header('Location: /admin/login.php', true, 302);
        exit;
    }
}

function quantlab_csrf_token(): string
{
    quantlab_admin_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function quantlab_csrf_check(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals(quantlab_csrf_token(), $token)) {
        http_response_code(400);
        exit('Неверный CSRF-токен. Обновите страницу.');
    }
}

function quantlab_admin_login(string $password): bool
{
    quantlab_admin_boot();
    $expected = quantlab_env('ADMIN_PASSWORD');
    if ($expected === '' || !hash_equals($expected, $password)) {
        return false;
    }
    $_SESSION['admin_ok'] = 1;
    session_regenerate_id(true);
    return true;
}

function quantlab_admin_logout(): void
{
    quantlab_admin_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/');
    }
    session_destroy();
}

function quantlab_admin_start(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    ?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= quantlab_h($title) ?></title>
    <meta name="robots" content="noindex,nofollow" />
    <?php quantlab_head_verification(); ?>
    <link rel="canonical" href="<?= quantlab_h(quantlab_abs_url('/admin/')) ?>" />
    <link rel="icon" href="<?= quantlab_icon_href() ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/css/styles.css" />
  </head>
  <body class="page-inner page-admin">
    <div class="noise" aria-hidden="true"></div>
    <header class="header">
      <div class="container header-inner">
        <a class="logo" href="/admin/">AM Quant<span>Lab</span> · админ</a>
        <nav class="nav" id="nav">
          <a href="/admin/">Статьи</a>
          <a href="/admin/leads.php">Заявки</a>
          <a href="/admin/edit.php">Новая</a>
          <a href="/blog/">Блог</a>
          <a href="/">Сайт</a>
          <a href="/admin/logout.php">Выйти</a>
        </nav>
      </div>
    </header>
    <main class="page-main">
      <div class="container">
    <?php
}

function quantlab_admin_storage_note(): string
{
    if (function_exists('quantlab_db') && quantlab_db()) {
        $name = quantlab_env('MYSQL_DATABASE');
        return '<p class="admin-storage">Хранение: MySQL · ' . quantlab_h($name) . '</p>';
    }
    $error = function_exists('quantlab_db_last_error') ? quantlab_db_last_error() : '';
    if (function_exists('quantlab_db_enabled') && quantlab_db_enabled() && $error !== '') {
        return '<p class="admin-storage admin-storage-warn">MySQL не подключился. Сейчас файлы. ' . quantlab_h($error) . '</p>';
    }
    return '<p class="admin-storage admin-storage-warn">Хранение: файлы JSON. База MySQL создастся сама при первом успешном подключении.</p>';
}

function quantlab_admin_mail_note(): string
{
    if (!function_exists('quantlab_mail_enabled') || !quantlab_mail_enabled()) {
        return '<p class="admin-storage admin-storage-warn">Почта: задайте SMTP_PASSWORD в .env для ящика на Timeweb.</p>';
    }
    $status = function_exists('quantlab_mail_status') ? quantlab_mail_status() : [];
    $to = quantlab_env('SMTP_TO');
    if (!empty($status['ok'])) {
        return '<p class="admin-storage">Почта: SMTP Timeweb → ' . quantlab_h($to) . '</p>';
    }
    if (!empty($status['error'])) {
        return '<p class="admin-storage admin-storage-warn">Почта не ушла: ' . quantlab_h((string) $status['error']) . '</p>';
    }
    return '<p class="admin-storage">Почта: SMTP готов, письма уйдут с новой заявки на ' . quantlab_h($to) . '</p>';
}

function quantlab_admin_end(string $extraJs = ''): void
{
    ?>
      </div>
    </main>
    <script>
      <?= $extraJs ?>
    </script>
  </body>
</html>
    <?php
}
