<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

$cli = PHP_SAPI === 'cli';
$token = quantlab_env('CRON_TOKEN');
$given = (string) ($_GET['token'] ?? '');
if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    if ($token === '' || $given === '' || !hash_equals($token, $given)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Нет доступа'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$state = quantlab_inbox_sync(true);
if (!$cli) {
    echo json_encode($state, JSON_UNESCAPED_UNICODE);
    exit;
}
$error = trim((string) ($state['error'] ?? ''));
echo (!empty($state['ok']) ? 'ok' : 'err')
    . ' imported=' . (int) ($state['imported'] ?? 0)
    . ($error !== '' ? ' ' . $error : '')
    . PHP_EOL;
