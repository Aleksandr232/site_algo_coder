<?php

require_once dirname(__DIR__) . '/lib/cache.php';
require_once dirname(__DIR__) . '/lib/http.php';

$id = preg_replace('/\D+/', '', (string) ($_GET['id'] ?? '131208'));
$kind = ($_GET['kind'] ?? 'strategy') === 'profit' ? 'profit' : 'strategy';
if ($id === '') {
    quantlab_send_error(400, 'bad strategy id');
}

$apiUrl = $kind === 'profit'
    ? 'https://www.comon.ru/api/v1/strategies/' . $id . '/profit'
    : 'https://www.comon.ru/api/v1/strategies/' . $id;
$cacheFile = $kind === 'profit'
    ? 'strategy-' . $id . '-profit.json'
    : 'strategy-' . $id . '.json';

$headers = [
    'Accept: application/json, text/plain, */*',
    'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
    'Origin: https://www.comon.ru',
    'Referer: https://www.comon.ru/strategies/' . $id . '/',
    'X-Requested-With: XMLHttpRequest',
];

try {
    $first = quantlab_http_get($apiUrl, $headers, true);
    if ($first['status'] < 200 || $first['status'] >= 300) {
        throw new RuntimeException('Comon ' . $first['status']);
    }
    $json = json_decode($first['body'], true, 512, JSON_THROW_ON_ERROR);
    if ($kind === 'profit' && empty($json['data'])) {
        throw new RuntimeException('Comon profit is empty');
    }
    quantlab_cache_write($cacheFile, $first['body']);
    quantlab_send_json(200, $first['body'], 'comon-live');
} catch (Throwable $e) {
    $cached = quantlab_cache_read($cacheFile);
    if ($cached !== null) {
        quantlab_send_json(200, $cached, 'cache', $e->getMessage());
    }
    quantlab_send_error(502, 'comon unavailable', $e->getMessage());
}
