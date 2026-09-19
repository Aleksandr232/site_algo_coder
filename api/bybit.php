<?php

require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/bybit.php';
require_once dirname(__DIR__) . '/lib/strategies.php';

function quantlab_bybit_request_config(): array
{
    $slug = preg_replace('/[^a-z0-9-]+/', '', strtolower((string) ($_GET['slug'] ?? '')));
    $row = $slug !== '' ? quantlab_strategy_load($slug) : null;
    if (!$row || ($row['venue'] ?? '') !== 'bybit') {
        foreach (quantlab_strategies_visible() as $item) {
            if (($item['venue'] ?? '') === 'bybit') {
                $row = $item;
                break;
            }
        }
    }
    if ($row && function_exists('quantlab_bybit_config_from_row')) {
        return quantlab_bybit_config_from_row($row);
    }
    return quantlab_bybit_config();
}

$cfg = quantlab_bybit_request_config();
$cacheName = 'bybit-case-' . preg_replace('/[^a-z0-9-]+/', '', (string) $cfg['slug']) . '.json';

try {
    $payload = quantlab_bybit_case($cfg);
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    quantlab_cache_write($cacheName, $text);
    if (($cfg['slug'] ?? '') === 'bybit-btc') {
        quantlab_cache_write('bybit-case.json', $text);
    }
    quantlab_send_json(200, $text, 'bybit-live');
} catch (Throwable $e) {
    $cached = quantlab_cache_read($cacheName) ?? quantlab_cache_read('bybit-case.json');
    if ($cached !== null) {
        quantlab_send_json(200, $cached, 'cache', $e->getMessage());
    }
    quantlab_send_error(502, 'bybit unavailable', $e->getMessage());
}
