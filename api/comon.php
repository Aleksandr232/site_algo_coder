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
$pageUrl = 'https://www.comon.ru/strategies/' . $id . '/';
$cacheFile = $kind === 'profit'
    ? 'strategy-' . $id . '-profit.json'
    : 'strategy-' . $id . '.json';

function quantlab_comon_headers(string $id, string $accept): array
{
    return [
        'Accept: ' . $accept,
        'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
        'Origin: https://www.comon.ru',
        'Referer: https://www.comon.ru/strategies/' . $id . '/',
        'X-Requested-With: XMLHttpRequest',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ];
}

function quantlab_comon_request(string $url, array $headers, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 6,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'Comon request failed');
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => (string) $body];
}

try {
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quantlab-comon-' . $id . '.txt';
    quantlab_comon_request($pageUrl, quantlab_comon_headers($id, 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'), $cookieFile);
    $first = quantlab_comon_request($apiUrl, quantlab_comon_headers($id, 'application/json, text/plain, */*'), $cookieFile);
    if ($first['status'] < 200 || $first['status'] >= 300) {
        throw new RuntimeException('Comon ' . $first['status']);
    }
    $json = json_decode($first['body'], true);
    if (!is_array($json)) {
        throw new RuntimeException('Comon bad JSON');
    }
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
