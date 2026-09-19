<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'site.php';

function quantlab_telegram_bot_token(): string
{
    return trim(quantlab_env('TELEGRAM_BOT_TOKEN', quantlab_env('TG_BOT_TOKEN')));
}

function quantlab_telegram_channel(): string
{
    return trim(quantlab_env(
        'TELEGRAM_CHANNEL_ID',
        quantlab_env('TELEGRAM_CHANNEL', quantlab_env('TG_CHANNEL_ID', quantlab_env('TG_CHANNEL')))
    ));
}

function quantlab_telegram_enabled(): bool
{
    return quantlab_telegram_bot_token() !== '' && quantlab_telegram_channel() !== '';
}

function quantlab_telegram_status_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'telegram-status.json';
}

function quantlab_telegram_sent_path(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'blog';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'telegram-sent.json';
}

function quantlab_telegram_status(?bool $ok = null, string $error = ''): array
{
    $path = quantlab_telegram_status_path();
    if ($ok !== null) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode([
            'ok' => $ok,
            'error' => $error,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
    if (!is_file($path)) {
        return ['ok' => null, 'error' => '', 'at' => null];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : ['ok' => null, 'error' => '', 'at' => null];
}

function quantlab_telegram_sent_map(): array
{
    $path = quantlab_telegram_sent_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function quantlab_telegram_sent_write(array $map): void
{
    file_put_contents(
        quantlab_telegram_sent_path(),
        json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_telegram_post_sent(string $slug): bool
{
    if ($slug === '') {
        return false;
    }
    $map = quantlab_telegram_sent_map();
    return !empty($map[$slug]);
}

function quantlab_telegram_mark_sent(string $slug): void
{
    if ($slug === '') {
        return;
    }
    $map = quantlab_telegram_sent_map();
    $map[$slug] = date('c');
    quantlab_telegram_sent_write($map);
}

function quantlab_telegram_unmark_sent(string $slug): void
{
    if ($slug === '') {
        return;
    }
    $map = quantlab_telegram_sent_map();
    if (!isset($map[$slug])) {
        return;
    }
    unset($map[$slug]);
    quantlab_telegram_sent_write($map);
}

function quantlab_telegram_rename_sent(string $from, string $to): void
{
    if ($from === '' || $to === '' || $from === $to) {
        return;
    }
    $map = quantlab_telegram_sent_map();
    if (empty($map[$from])) {
        return;
    }
    if (empty($map[$to])) {
        $map[$to] = $map[$from];
    }
    unset($map[$from]);
    quantlab_telegram_sent_write($map);
}

function quantlab_telegram_plain(string $markdown): string
{
    $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $markdown) ?? $markdown;
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
    $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
    $text = str_replace(['*', '_', '`', '>', '~'], '', $text);
    $text = preg_replace("/\r\n|\n|\r/", ' ', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

function quantlab_telegram_len(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function quantlab_telegram_clip(string $text, int $len): string
{
    if (function_exists('quantlab_clip')) {
        $cut = quantlab_clip($text, $len);
        if ($cut !== $text) {
            return rtrim($cut) . '…';
        }
        return $cut;
    }
    if (quantlab_telegram_len($text) <= $len) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($text, 0, $len - 1, 'UTF-8')) . '…';
    }
    return rtrim(substr($text, 0, $len - 1)) . '…';
}

function quantlab_telegram_clip_sentence(string $text, int $len): string
{
    $text = trim($text);
    if ($text === '' || quantlab_telegram_len($text) <= $len) {
        return $text;
    }
    $cut = rtrim(quantlab_telegram_clip($text, $len), " \t\n\r…");
    if (preg_match('/^(.*[.!?…])(?:\s|$)/us', $cut, $match) && quantlab_telegram_len($match[1]) >= (int) ($len * 0.4)) {
        return trim($match[1]);
    }
    return rtrim($cut) . '…';
}

function quantlab_telegram_html(string $text): string
{
    return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quantlab_telegram_has_price(string $text): bool
{
    if ($text === '') {
        return false;
    }
    if (preg_match('/[₽€$]|руб(?:\.|лей|ля|лях|ль)?|\bRUB\b|\bUSD\b/u', $text)) {
        return true;
    }
    if (preg_match('/\b(?:цен[аыу]|цен\b|стоимост[ьи]|прайс(?:-лист)?)\b/u', $text)) {
        return true;
    }
    if (preg_match('/сколько\s+стоит|стоит\s+\d/u', $text)) {
        return true;
    }
    if (preg_match('/от\s+\d[\d\s\x{00A0}]*(?:[.,]\d+)?(?:\s*(?:тыс(?:яч)?|к))?(?:\s*(?:₽|руб|дн(?:ей|я)?|день))?/u', $text)) {
        return true;
    }
    return false;
}

function quantlab_telegram_strip_prices(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/(?<=[.!?…])\s+/u', $text) ?: [$text];
    $keep = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || quantlab_telegram_has_price($part)) {
            continue;
        }
        $keep[] = $part;
    }
    $clean = trim(implode(' ', $keep));
    $clean = preg_replace('/от\s+\d[\d\s\x{00A0}]*(?:[.,]\d+)?(?:\s*(?:тыс(?:яч)?|к))?(?:\s*(?:₽|руб(?:лей|ля)?|дн(?:ей|я)?))?/u', '', $clean) ?? $clean;
    $clean = preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean;
    $clean = preg_replace('/\s+([,.;:!?])/u', '$1', $clean) ?? $clean;
    return trim($clean, " \t\n\r\0\x0B,;:—-");
}

function quantlab_telegram_body_preview(string $markdown): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $markdown);
    $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text) ?? $text;
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
    $text = preg_replace('/^#{1,6}\s+.*$/m', '', $text) ?? $text;
    $text = preg_replace('/^\|.+$/m', '', $text) ?? $text;
    $text = str_replace(['*', '_', '`', '~'], '', $text);
    $text = preg_replace('/^>\s?/m', '', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    $paragraphs = preg_split("/\n\s*\n/", trim($text)) ?: [];
    $chunks = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim(preg_replace('/\s+/', ' ', $paragraph) ?? $paragraph);
        $paragraph = quantlab_telegram_strip_prices($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $chunks[] = $paragraph;
        $joined = implode("\n\n", $chunks);
        if (count($chunks) >= 3 || quantlab_telegram_len($joined) >= 520) {
            break;
        }
    }

    return quantlab_telegram_clip_sentence(implode("\n\n", $chunks), 720);
}

function quantlab_telegram_caption(array $post): string
{
    $title = trim((string) ($post['title'] ?? ''));
    $titleHtml = '<b>' . quantlab_telegram_html($title) . '</b>';
    $budget = 1024 - quantlab_telegram_len($titleHtml) - 2;
    if ($budget < 120) {
        $budget = 120;
    }
    if ($budget > 720) {
        $budget = 720;
    }

    $preview = quantlab_telegram_body_preview((string) ($post['body'] ?? ''));
    if ($preview === '') {
        $preview = quantlab_telegram_strip_prices(trim((string) ($post['excerpt'] ?? '')));
    }
    if ($preview === '') {
        $preview = quantlab_telegram_strip_prices(trim((string) ($post['seo_description'] ?? '')));
    }
    $preview = quantlab_telegram_clip_sentence($preview, $budget);

    $caption = $titleHtml;
    if ($preview !== '') {
        $caption .= "\n\n" . quantlab_telegram_html($preview);
    }
    return $caption;
}

function quantlab_telegram_post_url(array $post): string
{
    $slug = trim((string) ($post['slug'] ?? ''));
    return quantlab_abs_url('blog/' . $slug);
}

function quantlab_telegram_keyboard(string $url): string
{
    return json_encode([
        'inline_keyboard' => [[
            [
                'text' => 'Читать на AM QuantLab',
                'url' => $url,
            ],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function quantlab_telegram_local_image(?string $webPath): ?string
{
    if (!$webPath) {
        return null;
    }
    if (preg_match('#^/uploads/blog/([a-zA-Z0-9._-]+)$#', $webPath, $match)) {
        $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . $match[1];
        return is_file($abs) ? $abs : null;
    }
    if (preg_match('#^/img/([a-zA-Z0-9._-]+)$#', $webPath, $match)) {
        $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $match[1];
        return is_file($abs) ? $abs : null;
    }
    return null;
}

function quantlab_telegram_mime(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function quantlab_telegram_api(string $method, array $fields): array
{
    $token = quantlab_telegram_bot_token();
    if ($token === '' || !preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
        throw new RuntimeException('Задайте TELEGRAM_BOT_TOKEN в .env');
    }
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $raw = quantlab_telegram_curl($url, $fields, true);
    if ($raw === null) {
        $raw = quantlab_telegram_curl($url, $fields, false);
    }
    if ($raw === null) {
        throw new RuntimeException('Telegram API недоступен');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Пустой ответ Telegram');
    }
    if (empty($data['ok'])) {
        $desc = trim((string) ($data['description'] ?? 'ошибка Telegram'));
        throw new RuntimeException($desc);
    }
    return $data;
}

function quantlab_telegram_curl(string $url, array $fields, bool $verifySsl): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($raw === false || $errno) {
        return null;
    }
    return (string) $raw;
}

function quantlab_telegram_share_post(array $post): array
{
    if (!quantlab_telegram_enabled()) {
        return ['ok' => false, 'error' => 'disabled'];
    }
    if (($post['status'] ?? '') !== 'published') {
        return ['ok' => false, 'error' => 'draft'];
    }

    $url = quantlab_telegram_post_url($post);
    $caption = quantlab_telegram_caption($post);
    $markup = quantlab_telegram_keyboard($url);
    $chat = quantlab_telegram_channel();
    $image = trim((string) ($post['image'] ?? ''));
    $local = quantlab_telegram_local_image($image !== '' ? $image : null);

    try {
        if ($local && class_exists('CURLFile')) {
            $mime = quantlab_telegram_mime($local);
            $file = new CURLFile($local, $mime, basename($local));
            $fields = [
                'chat_id' => $chat,
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => $markup,
            ];
            if (strpos($mime, 'gif') !== false) {
                $fields['animation'] = $file;
                quantlab_telegram_api('sendAnimation', $fields);
            } else {
                $fields['photo'] = $file;
                quantlab_telegram_api('sendPhoto', $fields);
            }
        } elseif ($image !== '') {
            quantlab_telegram_api('sendPhoto', [
                'chat_id' => $chat,
                'photo' => quantlab_abs_url($image),
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => $markup,
            ]);
        } else {
            quantlab_telegram_api('sendMessage', [
                'chat_id' => $chat,
                'text' => $caption,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
                'reply_markup' => $markup,
            ]);
        }
        quantlab_telegram_mark_sent((string) ($post['slug'] ?? ''));
        quantlab_telegram_status(true, '');
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($local && $image !== '') {
            try {
                quantlab_telegram_api('sendPhoto', [
                    'chat_id' => $chat,
                    'photo' => quantlab_abs_url($image),
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $markup,
                ]);
                quantlab_telegram_mark_sent((string) ($post['slug'] ?? ''));
                quantlab_telegram_status(true, '');
                return ['ok' => true, 'error' => ''];
            } catch (Throwable $fallback) {
                $error = $fallback->getMessage();
            }
        }
        try {
            quantlab_telegram_api('sendMessage', [
                'chat_id' => $chat,
                'text' => $caption,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
                'reply_markup' => $markup,
            ]);
            quantlab_telegram_mark_sent((string) ($post['slug'] ?? ''));
            quantlab_telegram_status(true, '');
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $last) {
            $error = $last->getMessage();
        }
        quantlab_telegram_status(false, $error);
        return ['ok' => false, 'error' => $error];
    }
}

function quantlab_telegram_message_id(array $data): int
{
    return (int) ($data['result']['message_id'] ?? 0);
}

function quantlab_telegram_author_state_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'telegram-author.json';
}

function quantlab_telegram_author_state(?array $write = null): array
{
    $path = quantlab_telegram_author_state_path();
    if ($write !== null) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($write, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        return $write;
    }
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function quantlab_telegram_author_url(): string
{
    return rtrim(quantlab_site_url(), '/') . '/#author';
}

function quantlab_telegram_author_keyboard(): string
{
    return json_encode([
        'inline_keyboard' => [[
            [
                'text' => 'Кто пишет роботов',
                'url' => quantlab_telegram_author_url(),
            ],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function quantlab_telegram_author_caption(): string
{
    $lines = [
        '<b>Кто пишет роботов в AM QuantLab</b>',
        '',
        'Александр, основатель. Больше 5 лет в разработке, три года — финтех и торговые алгоритмы.',
        '',
        'Раньше работал разработчиком у алготрейдера Ильи Петрова: роботы и контуры под живой счёт, не под презентацию. Поэтому знаю, как выглядят риск, API и журнал сделок изнутри.',
        '',
        'Пишу сам. Исполнение только через официальные API Финам, Тинькофф Инвестиции, Bybit, OKX и Binance. Не продаю сигналы и не обещаю чужую доходность.',
        '',
        'Есть свой контур. Это мой счёт, не гарантия по вашему.',
    ];
    return implode("\n", $lines);
}

function quantlab_telegram_pin_message(int $messageId): void
{
    if ($messageId <= 0) {
        throw new RuntimeException('Нет message_id для закрепа');
    }
    quantlab_telegram_api('pinChatMessage', [
        'chat_id' => quantlab_telegram_channel(),
        'message_id' => $messageId,
        'disable_notification' => 'true',
    ]);
}

function quantlab_telegram_unpin_message(int $messageId): void
{
    if ($messageId <= 0) {
        return;
    }
    try {
        quantlab_telegram_api('unpinChatMessage', [
            'chat_id' => quantlab_telegram_channel(),
            'message_id' => $messageId,
        ]);
    } catch (Throwable $e) {
        // уже снят или нет прав — не блокируем новый пост
    }
}

function quantlab_telegram_share_author(bool $pin = true): array
{
    if (!quantlab_telegram_enabled()) {
        return ['ok' => false, 'error' => 'disabled', 'message_id' => 0, 'pinned' => false];
    }

    $caption = quantlab_telegram_author_caption();
    $markup = quantlab_telegram_author_keyboard();
    $chat = quantlab_telegram_channel();
    $photo = '/img/author.jpg';
    $local = quantlab_telegram_local_image($photo);
    $messageId = 0;

    try {
        if ($local && class_exists('CURLFile')) {
            $sent = quantlab_telegram_api('sendPhoto', [
                'chat_id' => $chat,
                'photo' => new CURLFile($local, quantlab_telegram_mime($local), basename($local)),
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => $markup,
            ]);
        } else {
            $sent = quantlab_telegram_api('sendPhoto', [
                'chat_id' => $chat,
                'photo' => quantlab_abs_url($photo),
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => $markup,
            ]);
        }
        $messageId = quantlab_telegram_message_id($sent);
    } catch (Throwable $e) {
        try {
            $sent = quantlab_telegram_api('sendMessage', [
                'chat_id' => $chat,
                'text' => $caption,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'false',
                'reply_markup' => $markup,
            ]);
            $messageId = quantlab_telegram_message_id($sent);
        } catch (Throwable $last) {
            quantlab_telegram_status(false, $last->getMessage());
            return ['ok' => false, 'error' => $last->getMessage(), 'message_id' => 0, 'pinned' => false];
        }
    }

    $pinned = false;
    if ($pin && $messageId > 0) {
        $prev = (int) (quantlab_telegram_author_state()['message_id'] ?? 0);
        if ($prev > 0 && $prev !== $messageId) {
            quantlab_telegram_unpin_message($prev);
        }
        try {
            quantlab_telegram_pin_message($messageId);
            $pinned = true;
        } catch (Throwable $e) {
            quantlab_telegram_author_state([
                'ok' => true,
                'pinned' => false,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'at' => date('c'),
            ]);
            quantlab_telegram_status(true, '');
            return [
                'ok' => true,
                'error' => 'Пост ушёл, закреп не вышел: ' . $e->getMessage(),
                'message_id' => $messageId,
                'pinned' => false,
            ];
        }
    }

    quantlab_telegram_author_state([
        'ok' => true,
        'pinned' => $pinned,
        'message_id' => $messageId,
        'error' => '',
        'at' => date('c'),
    ]);
    quantlab_telegram_status(true, '');
    return ['ok' => true, 'error' => '', 'message_id' => $messageId, 'pinned' => $pinned];
}
