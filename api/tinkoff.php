<?php

require_once dirname(__DIR__) . '/lib/tinkoff.php';

try {
    $payload = quantlab_tinkoff_case();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    quantlab_cache_write('tinkoff-case.json', $text);
    quantlab_send_json(200, $text, 'tinkoff-live');
} catch (Throwable $e) {
    $cached = quantlab_cache_read('tinkoff-case.json');
    if ($cached !== null) {
        quantlab_send_json(200, $cached, 'cache', $e->getMessage());
    }
    quantlab_send_error(502, 'tinkoff unavailable', $e->getMessage());
}
