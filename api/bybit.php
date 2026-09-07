<?php

require_once dirname(__DIR__) . '/lib/bybit.php';

try {
    $payload = quantlab_bybit_case();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    quantlab_cache_write('bybit-case.json', $text);
    quantlab_send_json(200, $text, 'bybit-live');
} catch (Throwable $e) {
    $cached = quantlab_cache_read('bybit-case.json');
    if ($cached !== null) {
        quantlab_send_json(200, $cached, 'cache', $e->getMessage());
    }
    quantlab_send_error(502, 'bybit unavailable', $e->getMessage());
}
