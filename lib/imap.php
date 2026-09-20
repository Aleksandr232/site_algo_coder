<?php

function quantlab_imap_host(): string
{
    $configured = quantlab_env('IMAP_HOST');
    if ($configured !== '') {
        return $configured;
    }
    $smtp = function_exists('quantlab_smtp_host') ? quantlab_smtp_host() : 'smtp.timeweb.ru';
    if (str_starts_with($smtp, 'smtp.')) {
        return 'imap.' . substr($smtp, 5);
    }
    return 'imap.timeweb.ru';
}

function quantlab_imap_port(): int
{
    $port = (int) quantlab_env('IMAP_PORT', '993');
    return $port > 0 ? $port : 993;
}

function quantlab_imap_quote(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function quantlab_mail_own_addresses(): array
{
    $set = [];
    $candidates = [
        quantlab_env('SMTP_FROM'),
        quantlab_env('SMTP_USER'),
        function_exists('quantlab_site_email') ? quantlab_site_email() : '',
        'info@amquantlab.ru',
    ];
    foreach ($candidates as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && function_exists('quantlab_mail_is_email') && quantlab_mail_is_email($email)) {
            $set[$email] = $email;
        }
    }
    return $set;
}

function quantlab_inbox_state_path(): string
{
    return quantlab_data_dir() . DIRECTORY_SEPARATOR . 'inbox-state.json';
}

function quantlab_inbox_state(?array $set = null): array
{
    $path = quantlab_inbox_state_path();
    if ($set !== null) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($set, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        return $set;
    }
    if (!is_file($path)) {
        return [
            'ok' => null,
            'error' => '',
            'at' => null,
            'uidvalidity' => 0,
            'last_uid' => 0,
            'imported' => 0,
        ];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : ['ok' => null, 'error' => '', 'at' => null];
}

function quantlab_imap_read($fp, string $tag): string
{
    $data = '';
    while (($line = fgets($fp, 8192)) !== false) {
        $data .= $line;
        if (preg_match('/\{(\d+)\}\r?\n$/', $line, $match)) {
            $need = (int) $match[1];
            $chunk = '';
            while (strlen($chunk) < $need) {
                $got = fread($fp, $need - strlen($chunk));
                if ($got === false || $got === '') {
                    break;
                }
                $chunk .= $got;
            }
            $data .= $chunk;
            continue;
        }
        if (str_starts_with($line, $tag . ' ')) {
            break;
        }
    }
    if ($data === '') {
        throw new RuntimeException('IMAP не ответил');
    }
    if (preg_match('/^' . preg_quote($tag, '/') . ' NO /m', $data) || preg_match('/^' . preg_quote($tag, '/') . ' BAD /m', $data)) {
        throw new RuntimeException('IMAP: ' . trim(preg_replace('/^.*' . preg_quote($tag, '/') . ' (?:NO|BAD) /s', '', $data)));
    }
    return $data;
}

function quantlab_imap_cmd($fp, int &$n, string $command): string
{
    $n++;
    $tag = 'A' . $n;
    fwrite($fp, $tag . ' ' . $command . "\r\n");
    return quantlab_imap_read($fp, $tag);
}

function quantlab_imap_extract_rfc822(string $data): string
{
    if (preg_match('/BODY\[\]\s+\{(\d+)\}\r?\n/', $data, $match, PREG_OFFSET_CAPTURE)) {
        return substr($data, $match[0][1] + strlen($match[0][0]), (int) $match[1][0]);
    }
    if (preg_match('/BODY\[\]\s+"((?:\\\\.|[^"\\\\])*)"/s', $data, $match)) {
        return stripcslashes($match[1]);
    }
    return '';
}

function quantlab_mail_decode_header(string $value): string
{
    $value = preg_replace('/\r?\n[\t ]+/', ' ', $value) ?? $value;
    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($value, 2, 'UTF-8');
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }
    }
    return $value;
}

function quantlab_mail_extract_address(string $raw): string
{
    $raw = quantlab_mail_decode_header($raw);
    if (preg_match('/<([^>]+)>/', $raw, $match) && quantlab_mail_is_email(trim($match[1]))) {
        return strtolower(trim($match[1]));
    }
    $raw = trim($raw, " \t\"'");
    if (quantlab_mail_is_email($raw)) {
        return strtolower($raw);
    }
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $raw, $match) && quantlab_mail_is_email($match[0])) {
        return strtolower($match[0]);
    }
    return '';
}

function quantlab_mail_parse_headers(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $raw = preg_replace("/\n[\t ]+/", ' ', $raw) ?? $raw;
    $headers = [];
    foreach (explode("\n", $raw) as $line) {
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $colon)));
        $value = trim(substr($line, $colon + 1));
        if ($name === '') {
            continue;
        }
        if (isset($headers[$name])) {
            $headers[$name] .= ' ' . $value;
        } else {
            $headers[$name] = $value;
        }
    }
    return $headers;
}

function quantlab_mail_decode_transfer(string $body, string $encoding): string
{
    $encoding = strtolower(trim($encoding));
    if ($encoding === 'base64') {
        $decoded = base64_decode($body, true);
        return $decoded !== false ? $decoded : $body;
    }
    if ($encoding === 'quoted-printable') {
        return quoted_printable_decode($body);
    }
    return $body;
}

function quantlab_mail_to_utf8(string $text, string $charset): string
{
    $charset = strtoupper(trim($charset, " \"'"));
    if ($charset === '' || in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
        return $text;
    }
    if (function_exists('mb_convert_encoding')) {
        $out = @mb_convert_encoding($text, 'UTF-8', $charset);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    $out = @iconv($charset, 'UTF-8//IGNORE', $text);
    return is_string($out) ? $out : $text;
}

function quantlab_mail_html_to_text(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
    $html = preg_replace('#</p>#i', "\n\n", $html) ?? $html;
    $html = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace("/[ \t]+/", ' ', $html) ?? $html;
    return trim($html);
}

function quantlab_mail_best_text(string $contentType, string $body, string $encoding): string
{
    $charset = 'UTF-8';
    if (preg_match('/charset="?([^";\s]+)/i', $contentType, $match)) {
        $charset = $match[1];
    }
    $decoded = quantlab_mail_to_utf8(quantlab_mail_decode_transfer($body, $encoding), $charset);
    $ct = strtolower($contentType);
    if (str_contains($ct, 'multipart/') && preg_match('/boundary=("?)([^";\r\n]+)\1/i', $contentType, $match)) {
        $plain = '';
        $html = '';
        $parts = preg_split('/--' . preg_quote($match[2], '/') . '(?:--)?/', $decoded) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '--') {
                continue;
            }
            $parsed = quantlab_mail_parse_rfc822($part);
            $childType = strtolower((string) ($parsed['content_type'] ?? 'text/plain'));
            if (str_starts_with($childType, 'multipart/')) {
                $inner = quantlab_mail_best_text(
                    (string) $parsed['content_type'],
                    (string) $parsed['raw_body'],
                    (string) ($parsed['headers']['content-transfer-encoding'] ?? '')
                );
                if ($inner !== '') {
                    $plain = $plain !== '' ? $plain : $inner;
                }
                continue;
            }
            if (str_starts_with($childType, 'text/plain') && $plain === '') {
                $plain = (string) $parsed['text'];
            } elseif (str_starts_with($childType, 'text/html') && $html === '') {
                $html = quantlab_mail_html_to_text((string) $parsed['text']);
            }
        }
        return $plain !== '' ? $plain : $html;
    }
    if (str_contains($ct, 'text/html')) {
        return quantlab_mail_html_to_text($decoded);
    }
    return $decoded;
}

function quantlab_mail_parse_rfc822(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $split = preg_split("/\n\n/", $raw, 2) ?: [];
    $headers = quantlab_mail_parse_headers((string) ($split[0] ?? ''));
    $body = (string) ($split[1] ?? '');
    $contentType = (string) ($headers['content-type'] ?? 'text/plain');
    $encoding = (string) ($headers['content-transfer-encoding'] ?? '');
    $text = quantlab_mail_best_text($contentType, $body, $encoding);
    return [
        'headers' => $headers,
        'raw_body' => $body,
        'content_type' => $contentType,
        'from' => quantlab_mail_extract_address((string) ($headers['from'] ?? '')),
        'to' => quantlab_mail_extract_address((string) ($headers['to'] ?? '')),
        'subject' => trim(quantlab_mail_decode_header((string) ($headers['subject'] ?? ''))),
        'date' => (string) ($headers['date'] ?? ''),
        'message_id' => trim((string) ($headers['message-id'] ?? '')),
        'in_reply_to' => trim((string) ($headers['in-reply-to'] ?? '')),
        'references' => trim((string) ($headers['references'] ?? '')),
        'x_lead' => trim((string) ($headers['x-ql-lead'] ?? '')),
        'text' => $text,
    ];
}

function quantlab_mail_visible_text(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    $parts = preg_split(
        '/\n(?:On .{8,90}wrote:|-----Original Message-----|________________________________|From:\s+\S+@|>?\s*20\d{2}[.-]\d{2}[.-]\d{2}.{0,40}wrote)/i',
        $text,
        2
    );
    $cut = trim((string) ($parts[0] ?? $text));
    $cut = preg_replace('/\n>.*$/s', '', $cut) ?? $cut;
    $cut = trim($cut);
    $len = function_exists('mb_strlen') ? mb_strlen($cut) : strlen($cut);
    if ($len < 3) {
        return trim($text);
    }
    return $cut;
}

function quantlab_inbox_match_lead(array $mail): ?array
{
    $blob = trim(
        (string) ($mail['x_lead'] ?? '') . ' '
        . (string) ($mail['in_reply_to'] ?? '') . ' '
        . (string) ($mail['references'] ?? '')
    );
    if (preg_match('/ql\.lead\.(\d+)/', $blob, $match) || preg_match('/^\d+$/', $blob, $match)) {
        $lead = function_exists('quantlab_lead_by_id') ? quantlab_lead_by_id((int) $match[1]) : null;
        if ($lead) {
            return $lead;
        }
    }
    $from = strtolower((string) ($mail['from'] ?? ''));
    if ($from === '' || !function_exists('quantlab_lead_find_by_email')) {
        return null;
    }
    return quantlab_lead_find_by_email($from);
}

function quantlab_inbox_sync(bool $force = false): array
{
    if (!function_exists('quantlab_mail_enabled') || !quantlab_mail_enabled()) {
        return quantlab_inbox_state(['ok' => false, 'error' => 'SMTP/IMAP не настроен', 'at' => date('c'), 'imported' => 0]);
    }
    $state = quantlab_inbox_state();
    $at = strtotime((string) ($state['at'] ?? ''));
    if (!$force && $at && (time() - $at) < 20) {
        return $state;
    }

    $host = quantlab_imap_host();
    $port = quantlab_imap_port();
    $user = quantlab_env('SMTP_USER');
    $pass = quantlab_env('SMTP_PASSWORD');
    $own = quantlab_mail_own_addresses();
    $imported = 0;

    $remote = 'ssl://' . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        $state = [
            'ok' => false,
            'error' => 'Нет связи с IMAP ' . $host . ':' . $port . ' — ' . $errstr,
            'at' => date('c'),
            'uidvalidity' => (int) ($state['uidvalidity'] ?? 0),
            'last_uid' => (int) ($state['last_uid'] ?? 0),
            'imported' => 0,
        ];
        return quantlab_inbox_state($state);
    }
    stream_set_timeout($fp, 8);

    try {
        $n = 0;
        quantlab_imap_read($fp, '*');
        quantlab_imap_cmd($fp, $n, 'LOGIN ' . quantlab_imap_quote($user) . ' ' . quantlab_imap_quote($pass));
        $select = quantlab_imap_cmd($fp, $n, 'SELECT INBOX');
        $uidvalidity = 0;
        if (preg_match('/UIDVALIDITY\s+(\d+)/i', $select, $match)) {
            $uidvalidity = (int) $match[1];
        }
        $lastUid = (int) ($state['last_uid'] ?? 0);
        if ($uidvalidity > 0 && (int) ($state['uidvalidity'] ?? 0) !== $uidvalidity) {
            $lastUid = 0;
        }
        $since = gmdate('d-M-Y', time() - 60 * 60 * 24 * 45);
        $search = quantlab_imap_cmd($fp, $n, 'UID SEARCH SINCE ' . $since);
        $uids = [];
        if (preg_match('/^\* SEARCH[^\n]*/m', $search, $match)) {
            preg_match_all('/\d+/', $match[0], $nums);
            foreach ($nums[0] as $uid) {
                $uid = (int) $uid;
                if ($uid > $lastUid) {
                    $uids[] = $uid;
                }
            }
        }
        $uids = array_slice($uids, 0, 40);
        $maxUid = $lastUid;
        foreach ($uids as $uid) {
            $maxUid = max($maxUid, $uid);
            $fetch = quantlab_imap_cmd($fp, $n, 'UID FETCH ' . $uid . ' BODY.PEEK[]');
            $rfc = quantlab_imap_extract_rfc822($fetch);
            if ($rfc === '') {
                continue;
            }
            $mail = quantlab_mail_parse_rfc822($rfc);
            $from = (string) ($mail['from'] ?? '');
            if ($from === '' || isset($own[$from])) {
                continue;
            }
            $lead = quantlab_inbox_match_lead($mail);
            if (!$lead) {
                continue;
            }
            $body = quantlab_mail_visible_text((string) ($mail['text'] ?? ''));
            if ($body === '') {
                $body = '(пустое письмо)';
            }
            if (function_exists('quantlab_lead_thread_add')) {
                $added = quantlab_lead_thread_add((int) ($lead['id'] ?? 0), [
                    'dir' => 'in',
                    'kind' => 'reply',
                    'from' => $from,
                    'to' => (string) ($mail['to'] ?? ''),
                    'subject' => (string) ($mail['subject'] ?? ''),
                    'body' => function_exists('mb_substr') ? mb_substr($body, 0, 20000) : substr($body, 0, 20000),
                    'at' => !empty($mail['date']) && strtotime((string) $mail['date'])
                        ? date('c', strtotime((string) $mail['date']))
                        : date('c'),
                    'message_id' => (string) ($mail['message_id'] ?? ''),
                    'in_reply_to' => (string) ($mail['in_reply_to'] ?? ''),
                    'read' => false,
                    'imap_uid' => $uid,
                ]);
                if ($added) {
                    $imported++;
                }
            }
        }
        try {
            quantlab_imap_cmd($fp, $n, 'LOGOUT');
        } catch (Throwable $e) {
            // ящик уже мог закрыть соединение
        }
        $state = [
            'ok' => true,
            'error' => '',
            'at' => date('c'),
            'uidvalidity' => $uidvalidity,
            'last_uid' => $maxUid,
            'imported' => $imported,
        ];
        return quantlab_inbox_state($state);
    } catch (Throwable $e) {
        $state = [
            'ok' => false,
            'error' => $e->getMessage(),
            'at' => date('c'),
            'uidvalidity' => (int) ($state['uidvalidity'] ?? 0),
            'last_uid' => (int) ($state['last_uid'] ?? 0),
            'imported' => 0,
        ];
        return quantlab_inbox_state($state);
    } finally {
        fclose($fp);
    }
}
