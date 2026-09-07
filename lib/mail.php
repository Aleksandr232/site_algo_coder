<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_smtp_host(): string
{
    return quantlab_env('SMTP_HOST', 'smtp.timeweb.ru');
}

function quantlab_smtp_port(): int
{
    $port = (int) quantlab_env('SMTP_PORT', '465');
    return $port > 0 ? $port : 465;
}

function quantlab_smtp_to(): string
{
    return quantlab_env('SMTP_TO', 'mealeksandr68@gmail.com, info@amquantlab.ru');
}

function quantlab_smtp_recipients(): array
{
    $parts = preg_split('/[,;]+/', quantlab_smtp_to()) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $email = trim($part);
        if (quantlab_mail_is_email($email)) {
            $emails[$email] = $email;
        }
    }
    return array_values($emails);
}

function quantlab_mail_enabled(): bool
{
    return quantlab_env('SMTP_USER') !== '' && quantlab_env('SMTP_PASSWORD') !== '';
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

function quantlab_mail_qp(string $text): string
{
    $encoded = quoted_printable_encode($text);
    $encoded = str_replace("\r\n", "\n", $encoded);
    return str_replace("\n", "\r\n", $encoded);
}

function quantlab_mail_send(string $subject, string $text, string $html = '', ?string $replyTo = null): void
{
    if (!quantlab_mail_enabled()) {
        throw new RuntimeException('SMTP не настроен: укажите SMTP_USER и SMTP_PASSWORD в .env');
    }

    $host = quantlab_smtp_host();
    $port = quantlab_smtp_port();
    $user = quantlab_env('SMTP_USER');
    $pass = quantlab_env('SMTP_PASSWORD');
    $from = quantlab_env('SMTP_FROM', $user);
    $fromName = quantlab_env('SMTP_FROM_NAME', 'AM QuantLab');
    $recipients = quantlab_smtp_recipients();

    if (!quantlab_mail_is_email($from) || !quantlab_mail_is_email($user) || !$recipients) {
        throw new RuntimeException('Некорректный email в SMTP_FROM / SMTP_TO / SMTP_USER');
    }

    $domain = substr(strrchr($from, '@') ?: '@amquantlab.ru', 1);
    $messageId = '<ql.' . date('YmdHis') . '.' . bin2hex(random_bytes(8)) . '@' . $domain . '>';
    $toHeader = implode(', ', $recipients);
    $headers = [
        'Date: ' . date('r'),
        'From: ' . quantlab_mail_header_value($fromName) . ' <' . $from . '>',
        'Sender: ' . $from,
        'To: ' . $toHeader,
        'Reply-To: ' . ($replyTo && quantlab_mail_is_email($replyTo) ? $replyTo : $from),
        'Subject: ' . quantlab_mail_header_value($subject),
        'Message-ID: ' . $messageId,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'Content-Language: ru',
    ];

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . quantlab_mail_qp($text) . "\r\n";
    $ports = [$port];
    if ($port === 465) {
        $ports[] = 587;
    }
    $lastError = 'Нет связи с SMTP';
    foreach ($ports as $tryPort) {
        try {
            quantlab_smtp_deliver($host, $tryPort, $domain, $user, $pass, $from, $recipients, $payload);
            quantlab_mail_status(true, '');
            return;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }
    quantlab_mail_status(false, $lastError);
    throw new RuntimeException($lastError);
}

function quantlab_smtp_deliver(
    string $host,
    int $port,
    string $domain,
    string $user,
    string $pass,
    string $from,
    array $recipients,
    string $payload
): void {
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
        throw new RuntimeException('Нет связи с SMTP ' . $host . ':' . $port . ' — ' . $errstr);
    }
    stream_set_timeout($fp, 20);
    try {
        quantlab_smtp_expect($fp, null, 220);
        quantlab_smtp_expect($fp, 'EHLO ' . $domain, 250);
        if ($port !== 465) {
            quantlab_smtp_expect($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS не включился');
            }
            quantlab_smtp_expect($fp, 'EHLO ' . $domain, 250);
        }
        quantlab_smtp_expect($fp, 'AUTH LOGIN', 334);
        quantlab_smtp_expect($fp, base64_encode($user), 334);
        quantlab_smtp_expect($fp, base64_encode($pass), 235);
        quantlab_smtp_expect($fp, 'MAIL FROM:<' . $from . '>', 250);
        foreach ($recipients as $rcpt) {
            quantlab_smtp_expect($fp, 'RCPT TO:<' . $rcpt . '>', 250, 251);
        }
        quantlab_smtp_expect($fp, 'DATA', 354);
        fwrite($fp, $payload);
        if (!str_ends_with($payload, "\r\n")) {
            fwrite($fp, "\r\n");
        }
        quantlab_smtp_expect($fp, '.', 250);
        quantlab_smtp_expect($fp, 'QUIT', 221, 250);
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

    $subject = 'Новая заявка: ' . $name;
    $text = "Здравствуйте.\r\n\r\n"
        . "На amquantlab.ru оставили заявку.\r\n\r\n"
        . "Дата: {$when}\r\n"
        . "Имя: {$name}\r\n"
        . "Контакт: {$contact}\r\n"
        . "Рынок: {$market}\r\n"
        . "Задача:\r\n{$message}\r\n\r\n"
        . "— AM QuantLab, {$site}\r\n";

    quantlab_mail_send($subject, $text);
}
