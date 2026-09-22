<?php

declare(strict_types=1);

@set_time_limit(90);
@ignore_user_abort(true);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

$cli = PHP_SAPI === 'cli';
$given = trim((string) ($_GET['token'] ?? $_GET['key'] ?? ''));
if ($cli && $given === '' && isset($argv[1])) {
    $given = trim((string) $argv[1]);
}

if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    if (!function_exists('quantlab_cron_token_ok') || !quantlab_cron_token_ok($given)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Нет доступа. Нужен GET ?token='], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif (function_exists('quantlab_cron_token') && quantlab_cron_token() !== '' && $given !== '' && !quantlab_cron_token_ok($given)) {
    fwrite(STDERR, "bad token\n");
    exit(1);
}

$state = quantlab_inbox_sync(true, 1);
$payload = [
    'ok' => !empty($state['ok']),
    'imported' => (int) ($state['imported'] ?? 0),
    'notified' => (int) ($state['notified'] ?? 0),
    'last_uid' => (int) ($state['last_uid'] ?? 0),
    'busy' => !empty($state['busy']),
    'error' => trim((string) ($state['error'] ?? '')),
    'at' => (string) ($state['at'] ?? date('c')),
];

if (!$cli) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

echo (!empty($payload['ok']) ? 'ok' : 'err')
    . ' imported=' . $payload['imported']
    . ' notified=' . $payload['notified']
    . ($payload['error'] !== '' ? ' ' . $payload['error'] : '')
    . PHP_EOL;
