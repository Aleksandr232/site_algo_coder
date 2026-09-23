<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Yield-Token, X-Api-Key');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method === 'PUT') {
    $method = 'POST';
}

$slug = quantlab_yield_slug();
$robot = function_exists('quantlab_strategy_by_yield_token')
    ? quantlab_strategy_by_yield_token(quantlab_yield_token_from_request())
    : null;
if ($robot) {
    $slug = (string) $robot['slug'];
}
$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$wantsJson = str_contains($accept, 'application/json')
    || isset($_GET['format'])
    || $method === 'POST';

function quantlab_yield_reply(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'POST') {
    $auth = quantlab_yield_resolve_write();
    if ($auth === [] || empty($auth['ok'])) {
        quantlab_yield_reply(403, ['ok' => false, 'error' => 'Нужен ключ этого робота: token в query, заголовок X-Yield-Token или Authorization: Bearer']);
    }
    $body = quantlab_yield_read_body();
    if (!empty($auth['master'])) {
        $slug = quantlab_yield_slug((string) ($body['slug'] ?? $slug));
    } else {
        $slug = (string) $auth['slug'];
    }
    if ($body === []) {
        quantlab_yield_reply(400, [
            'ok' => false,
            'error' => 'Пустое тело. Пришлите JSON с returnPercent и points или свой объект с equity, balance, series.',
        ]);
    }
    try {
        $parsed = quantlab_yield_parse_payload($body);
        $saved = quantlab_yield_save($slug, $parsed, $body);
        quantlab_yield_reply(200, array_merge(quantlab_yield_public($saved), [
            'accepted' => true,
            'message' => 'Доходность записана. GET без токена отдаёт точки для графика.',
        ]));
    } catch (Throwable $e) {
        quantlab_yield_reply(500, ['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($method !== 'GET') {
    quantlab_yield_reply(405, ['ok' => false, 'error' => 'Только GET и POST']);
}

if (!$wantsJson && !isset($_GET['slug']) && !isset($_GET['token'])) {
    header('Content-Type: text/html; charset=utf-8');
    $postUrl = quantlab_yield_post_url('main');
    $getUrl = quantlab_yield_get_url('main');
    quantlab_render_start([
        'title' => 'API доходности — AM QuantLab',
        'description' => 'Приём доходности робота: POST пишет точки, GET отдаёт серию для графика.',
        'canonical' => quantlab_abs_url('/api/yield/'),
        'robots' => 'noindex, follow',
        'body_class' => 'page-inner page-legal',
    ]);
    ?>
      <article class="container article-page">
        <p class="eyebrow">API</p>
        <h1>Доходность для графика</h1>
        <p class="lead">Робот шлёт POST. Сайт читает GET и рисует кривую. Дата — Москва.</p>
        <div class="prose">
          <h2>POST — записать</h2>
          <p>URL для отправки в роботе:</p>
          <p><code><?= quantlab_h($postUrl) ?></code></p>
          <p>Если тело пустое — ошибка. Стандартный JSON:</p>
          <pre>{
  "returnPercent": 12.4,
  "equity": 11240,
  "balance": 11000,
  "realizedPnl": 240,
  "running": true,
  "points": [
    {"date": "2026-09-01", "equity": 10000, "returnPercent": 0},
    {"date": "2026-09-23", "equity": 11240, "returnPercent": 12.4}
  ]
}</pre>
          <p>Своё тело, числа без кавычек:</p>
          <pre>{
  "date": "{{date}}",
  "return_percent": {{returnPercent}},
  "equity": {{equity}},
  "balance": {{balance}},
  "series": {{points}}
}</pre>
          <p>Подстановки робота: <code>{{date}}</code> день по Москве, <code>{{time}}</code>, <code>{{returnPercent}}</code>, <code>{{equity}}</code>, <code>{{balance}}</code>, <code>{{realizedPnl}}</code>, <code>{{running}}</code>, <code>{{points}}</code> — массив date / equity / returnPercent.</p>
          <p>Токен можно передать query <code>token=</code>, заголовком <code>X-Yield-Token</code> или <code>Authorization: Bearer</code>. У стратегии Forex в админке свой ключ: им робот пишет только в этот слаг.</p>
          <h2>GET — график</h2>
          <p>Публично, без токена:</p>
          <p><code><?= quantlab_h($getUrl) ?></code></p>
          <p>Ответ: <code>returnPercent</code>, <code>equity</code>, <code>series</code> с полями <code>date</code>, <code>value</code>, <code>equity</code>. <code>value</code> — доходность в % для линии на сайте.</p>
        </div>
      </article>
    <?php
    quantlab_render_end();
    exit;
}

$row = quantlab_yield_load($slug);
quantlab_yield_reply(200, quantlab_yield_public($row));
