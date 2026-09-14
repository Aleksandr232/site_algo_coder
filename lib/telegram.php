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

function quantlab_telegram_clip(string $text, int $len): string
{
    if (function_exists('quantlab_clip')) {
        $cut = quantlab_clip($text, $len);
        if ($cut !== $text) {
            return rtrim($cut) . '…';
        }
        return $cut;
    }
    if (function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $len) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $len - 1, 'UTF-8')) . '…';
    }
    return strlen($text) <= $len ? $text : rtrim(substr($text, 0, $len - 1)) . '…';
}

function quantlab_telegram_html(string $text): string
{
    return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quantlab_telegram_caption(array $post): string
{
    $title = trim((string) ($post['title'] ?? ''));
    $excerpt = trim((string) ($post['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = trim((string) ($post['seo_description'] ?? ''));
    }
    if ($excerpt === '') {
        $excerpt = quantlab_telegram_plain((string) ($post['body'] ?? ''));
    }
    $excerpt = quantlab_telegram_clip($excerpt, 280);

    $caption = '<b>' . quantlab_telegram_html($title) . '</b>';
    if ($excerpt !== '') {
        $caption .= "\n\n" . quantlab_telegram_html($excerpt);
    }
    if (function_exists('mb_strlen') && mb_strlen($caption, 'UTF-8') > 1024) {
        $caption = mb_substr($caption, 0, 1023, 'UTF-8');
    } elseif (strlen($caption) > 1024) {
        $caption = substr($caption, 0, 1023);
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
    if (!$webPath || !preg_match('#^/uploads/blog/([a-zA-Z0-9._-]+)$#', $webPath, $match)) {
        return null;
    }
    $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . $match[1];
    return is_file($abs) ? $abs : null;
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
