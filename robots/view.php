<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

$slug = isset($quantlab_slug) ? (string) $quantlab_slug : trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || !quantlab_is_slug($slug)) {
    http_response_code(404);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

quantlab_render_ready_page($slug);
