<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_mail_enabled(): bool
{
    return quantlab_env('SMTP_HOST') !== ''
        && quantlab_env('SMTP_USER') !== ''
        && quantlab_env('SMTP_PASSWORD') !== ''
        && quantlab_env('SMTP_TO') !== '';
}

function quantlab_mail_status(?bool $ok = null, string $error = ''): array
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mail-status.json';
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

function quantlab_mail_header_value(string $text): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function quantlab_mail_is_email(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
}

function quantlab_smtp_expect($fp, ?string $command, int ...$okCodes): string
{
    if ($command !== null) {
        fwrite($fp, $command . "\r\n");
    }
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (preg_match('/^\d{3}[\s-]/', $line) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($data, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP ' . ($code ?: 'timeout') . ': ' . trim($data));
    }
    return $data;
}

function quantlab_mail_send(string $subject, string $text, string $html, ?string $replyTo = null): void
{
    if (!quantlab_mail_enabled()) {
        throw new RuntimeException('SMTP не настроен: укажите SMTP_USER и SMTP_PASSWORD в .env');
    }

    $host = quantlab_env('SMTP_HOST', 'smtp.timeweb.ru');
    $port = (int) quantlab_env('SMTP_PORT', '465');
    $user = quantlab_env('SMTP_USER');
    $pass = quantlab_env('SMTP_PASSWORD');
    $from = quantlab_env('SMTP_FROM', $user);
    $fromName = quantlab_env('SMTP_FROM_NAME', 'AM QuantLab');
    $to = quantlab_env('SMTP_TO');

    if (!quantlab_mail_is_email($from) || !quantlab_mail_is_email($to) || !quantlab_mail_is_email($user)) {
        throw new RuntimeException('Некорректный email в SMTP_FROM / SMTP_TO / SMTP_USER');
    }

    $domain = substr(strrchr($from, '@') ?: '@amquantlab.ru', 1);
    $boundary = 'ql' . bin2hex(random_bytes(12));
    $messageId = '<ql-' . bin2hex(random_bytes(10)) . '@' . $domain . '>';
    $date = date('r');

    $headers = [
        'Date: ' . $date,
        'From: ' . quantlab_mail_header_value($fromName) . ' <' . $from . '>',
        'To: ' . $to,
        'Subject: ' . quantlab_mail_header_value($subject),
        'Message-ID: ' . $messageId,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Content-Language: ru',
        'Auto-Submitted: auto-generated',
        'X-Auto-Response-Suppress: All',
        'X-Priority: 3',
        'X-Mailer: AM-QuantLab',
    ];
    if ($replyTo && quantlab_mail_is_email($replyTo)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . '--' . $boundary . "--\r\n";

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException('Нет связи с SMTP: ' . $errstr);
    }
    stream_set_timeout($fp, 20);

    try {
        quantlab_smtp_expect($fp, null, 220);
        quantlab_smtp_expect($fp, 'EHLO ' . $domain, 250);
        quantlab_smtp_expect($fp, 'AUTH LOGIN', 334);
        quantlab_smtp_expect($fp, base64_encode($user), 334);
        quantlab_smtp_expect($fp, base64_encode($pass), 235);
        quantlab_smtp_expect($fp, 'MAIL FROM:<' . $from . '>', 250);
        quantlab_smtp_expect($fp, 'RCPT TO:<' . $to . '>', 250, 251);
        quantlab_smtp_expect($fp, 'DATA', 354);
        fwrite($fp, $payload);
        if (!str_ends_with($payload, "\r\n")) {
            fwrite($fp, "\r\n");
        }
        quantlab_smtp_expect($fp, '.', 250);
        quantlab_smtp_expect($fp, 'QUIT', 221, 250);
        quantlab_mail_status(true, '');
    } finally {
        fclose($fp);
    }
}

function quantlab_lead_mail(array $lead): void
{
    $name = (string) ($lead['name'] ?? '');
    $contact = (string) ($lead['contact'] ?? '');
    $market = function_exists('quantlab_lead_market_label')
        ? quantlab_lead_market_label((string) ($lead['market'] ?? ''))
        : (string) ($lead['market'] ?? '');
    $message = (string) ($lead['message'] ?? '');
    $when = date('d.m.Y H:i');
    $site = function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru';

    $subject = 'Заявка с сайта AM QuantLab — ' . $name;
    $text = "Новая заявка с сайта AM QuantLab\n\n"
        . "Дата: {$when}\n"
        . "Имя: {$name}\n"
        . "Контакт: {$contact}\n"
        . "Рынок: {$market}\n"
        . "Задача:\n{$message}\n\n"
        . "Админка: {$site}/admin/leads.php\n";

    $html = '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Заявка</title></head>'
        . '<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">'
        . '<tr><td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">'
        . '<div style="font-size:13px;color:#667085;">AM QuantLab</div>'
        . '<div style="font-size:20px;font-weight:700;margin-top:4px;">Новая заявка с сайта</div>'
        . '</td></tr><tr><td style="padding:20px 24px;font-size:15px;line-height:1.5;">'
        . '<p style="margin:0 0 10px;"><b>Дата:</b> ' . htmlspecialchars($when, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 10px;"><b>Имя:</b> ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 10px;"><b>Контакт:</b> ' . htmlspecialchars($contact, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 10px;"><b>Рынок:</b> ' . htmlspecialchars($market, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 6px;"><b>Задача:</b></p>'
        . '<p style="margin:0;white-space:pre-wrap;">' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        . '</td></tr><tr><td style="padding:16px 24px;border-top:1px solid #e5e7eb;font-size:13px;color:#667085;">'
        . 'Письмо отправлено с ' . htmlspecialchars($site, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</td></tr></table></td></tr></table></body></html>';

    $replyTo = quantlab_mail_is_email($contact) ? $contact : null;
    quantlab_mail_send($subject, $text, $html, $replyTo);
}
