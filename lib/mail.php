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

function quantlab_mail_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quantlab_mail_nl2html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return nl2br(quantlab_mail_esc($text), false);
}

function quantlab_mail_site(): string
{
    return function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru';
}

function quantlab_mail_abs(string $path): string
{
    if (function_exists('quantlab_abs_url')) {
        return quantlab_abs_url($path);
    }
    return rtrim(quantlab_mail_site(), '/') . '/' . ltrim($path, '/');
}

function quantlab_mail_boundary(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function quantlab_mail_robot_src(): string
{
    return quantlab_mail_abs('/img/scroll-robot.png');
}

function quantlab_mail_html_rows(array $rows): string
{
    if (!$rows) {
        return '';
    }
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 8px;border-collapse:collapse;">';
    foreach ($rows as $label => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $html .= '<tr>'
            . '<td style="padding:8px 12px 8px 0;width:118px;color:#8d97ab;font-size:13px;vertical-align:top;border-bottom:1px solid #1b2433;font-family:Arial,Helvetica,sans-serif;">'
            . quantlab_mail_esc((string) $label)
            . '</td>'
            . '<td style="padding:8px 0;color:#e8edf5;font-size:14px;vertical-align:top;border-bottom:1px solid #1b2433;font-family:Arial,Helvetica,sans-serif;">'
            . nl2br(quantlab_mail_esc($value), false)
            . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

function quantlab_mail_layout(array $opts): string
{
    $site = quantlab_mail_site();
    $email = function_exists('quantlab_site_email') ? quantlab_site_email() : 'info@amquantlab.ru';
    $legal = function_exists('quantlab_footer_legal')
        ? quantlab_footer_legal()
        : '© 2026 AM QuantLab.';
    $title = (string) ($opts['title'] ?? 'AM QuantLab');
    $eyebrow = (string) ($opts['eyebrow'] ?? 'AM QUANTLAB');
    $preheader = (string) ($opts['preheader'] ?? $title);
    $intro = (string) ($opts['intro'] ?? '');
    $body = (string) ($opts['body'] ?? '');
    $rows = is_array($opts['rows'] ?? null) ? $opts['rows'] : [];
    $ctaHref = (string) ($opts['cta_href'] ?? $site);
    $ctaLabel = (string) ($opts['cta_label'] ?? 'Открыть amquantlab.ru');
    $note = (string) ($opts['note'] ?? '');
    $robotSrc = quantlab_mail_robot_src();
    $logoSrc = quantlab_mail_abs('/favicon-96.png');

    $introHtml = $intro !== ''
        ? '<p style="margin:0 0 12px;color:#c8d0de;font-size:15px;line-height:1.6;font-family:Arial,Helvetica,sans-serif;">' . quantlab_mail_nl2html($intro) . '</p>'
        : '';
    $bodyHtml = $body !== ''
        ? '<p style="margin:16px 0 0;color:#e8edf5;font-size:15px;line-height:1.65;font-family:Arial,Helvetica,sans-serif;">' . quantlab_mail_nl2html($body) . '</p>'
        : '';
    $noteHtml = $note !== ''
        ? '<p style="margin:18px 0 0;color:#8d97ab;font-size:13px;line-height:1.55;font-family:Arial,Helvetica,sans-serif;">' . quantlab_mail_nl2html($note) . '</p>'
        : '';

    return '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . quantlab_mail_esc($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#05070c;color:#e8edf5;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . quantlab_mail_esc($preheader) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#05070c;background-color:#05070c;">
<tr><td align="center" style="padding:28px 12px 40px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#0b1018;background-color:#0b1018;border:1px solid #1b2433;">
<tr><td style="height:4px;background:#3dffa4;background-color:#3dffa4;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td style="padding:22px 28px 4px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="font-family:Arial,Helvetica,sans-serif;">
<a href="' . quantlab_mail_esc($site) . '" style="text-decoration:none;color:#e8edf5;white-space:nowrap;">
<img src="' . quantlab_mail_esc($logoSrc) . '" width="28" height="28" alt="" style="border:0;vertical-align:middle;display:inline-block;">
<span style="font-size:18px;font-weight:700;letter-spacing:-0.03em;vertical-align:middle;padding-left:8px;">AM Quant<span style="color:#3dffa4;">Lab</span></span>
</a>
</td>
<td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#3dffa4;font-size:11px;letter-spacing:0.08em;white-space:nowrap;">&#9679;&nbsp;live</td>
</tr></table>
</td></tr>
<tr><td align="center" style="padding:12px 28px 0;">
<img src="' . quantlab_mail_esc($robotSrc) . '" width="168" height="224" alt="Робот AM QuantLab" style="display:block;border:0;width:168px;height:auto;">
</td></tr>
<tr><td style="padding:4px 28px 8px;font-family:Arial,Helvetica,sans-serif;">
<p style="margin:0 0 8px;color:#3dffa4;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;">' . quantlab_mail_esc($eyebrow) . '</p>
<h1 style="margin:0 0 14px;color:#e8edf5;font-size:24px;line-height:1.25;font-weight:700;letter-spacing:-0.03em;">' . quantlab_mail_esc($title) . '</h1>
' . $introHtml . quantlab_mail_html_rows($rows) . $bodyHtml . $noteHtml . '
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 6px;"><tr>
<td style="background:#3dffa4;background-color:#3dffa4;border-radius:10px;">
<a href="' . quantlab_mail_esc($ctaHref) . '" style="display:inline-block;padding:12px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#05070c;text-decoration:none;">' . quantlab_mail_esc($ctaLabel) . '</a>
</td>
</tr></table>
</td></tr>
<tr><td style="padding:18px 28px 24px;border-top:1px solid #1b2433;font-family:Arial,Helvetica,sans-serif;">
<p style="margin:0 0 8px;color:#8d97ab;font-size:12px;line-height:1.55;">Роботы на Node.js и Go. API Финам, Тинькофф Инвестиции, Bybit, OKX, Binance.</p>
<p style="margin:0 0 8px;color:#8d97ab;font-size:12px;">
<a href="' . quantlab_mail_esc($site) . '" style="color:#3dffa4;text-decoration:none;">amquantlab.ru</a>
&nbsp;&middot;&nbsp;
<a href="mailto:' . quantlab_mail_esc($email) . '" style="color:#3dffa4;text-decoration:none;">' . quantlab_mail_esc($email) . '</a>
</p>
<p style="margin:0;color:#5d6678;font-size:11px;line-height:1.5;">' . quantlab_mail_esc($legal) . ' Материал не является индивидуальной инвестиционной рекомендацией.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

function quantlab_mail_payload(array $headers, string $text, string $html): string
{
    $html = trim($html);
    if ($html === '') {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: quoted-printable';
        return implode("\r\n", $headers) . "\r\n\r\n" . quantlab_mail_qp($text) . "\r\n";
    }

    $alt = quantlab_mail_boundary('alt');
    $altBody = '--' . $alt . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quantlab_mail_qp($text) . "\r\n"
        . '--' . $alt . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quantlab_mail_qp($html) . "\r\n"
        . '--' . $alt . "--\r\n";

    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
    return implode("\r\n", $headers) . "\r\n\r\n" . $altBody;
}

function quantlab_mail_send(string $subject, string $text, string $html = '', ?string $replyTo = null, ?array $to = null): void
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
    $recipients = [];
    foreach ($to ?? quantlab_smtp_recipients() as $email) {
        $email = trim((string) $email);
        if (quantlab_mail_is_email($email)) {
            $recipients[$email] = $email;
        }
    }
    $recipients = array_values($recipients);

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
        'Content-Language: ru',
    ];

    $payload = quantlab_mail_payload($headers, $text, $html);
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

function quantlab_lead_email(array $lead): string
{
    $candidates = [
        (string) ($lead['email'] ?? ''),
        (string) ($lead['contact'] ?? ''),
    ];
    foreach ($candidates as $raw) {
        $raw = trim($raw);
        if ($raw === '') {
            continue;
        }
        if (quantlab_mail_is_email($raw)) {
            return $raw;
        }
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $raw, $match) && quantlab_mail_is_email($match[0])) {
            return $match[0];
        }
    }
    return '';
}

function quantlab_lead_mail(array $lead): void
{
    $name = (string) ($lead['name'] ?? '');
    $contact = (string) ($lead['contact'] ?? '');
    $email = quantlab_lead_email($lead);
    $market = function_exists('quantlab_lead_market_label')
        ? quantlab_lead_market_label((string) ($lead['market'] ?? ''))
        : (string) ($lead['market'] ?? '');
    $message = (string) ($lead['message'] ?? '');
    $robotTitle = (string) ($lead['robot_title'] ?? '');
    $robotPrice = (string) ($lead['robot_price'] ?? '');
    $robotSlug = (string) ($lead['robot_slug'] ?? '');
    $when = date('d.m.Y H:i');
    $site = function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru';

    $subject = $robotTitle !== '' ? ('Заказ робота: ' . $robotTitle) : ('Новая заявка: ' . $name);
    $text = "Здравствуйте.\r\n\r\n"
        . ($robotTitle !== ''
            ? "На amquantlab.ru оформили готового робота.\r\n\r\n"
            : "На amquantlab.ru оставили заявку.\r\n\r\n")
        . "Дата: {$when}\r\n"
        . "Имя: {$name}\r\n"
        . "Контакт: {$contact}\r\n"
        . ($email !== '' && $email !== $contact ? "Почта: {$email}\r\n" : '')
        . "Рынок: {$market}\r\n";
    if ($robotTitle !== '') {
        $text .= "Робот: {$robotTitle}\r\n"
            . "Цена: {$robotPrice}\r\n"
            . ($robotSlug !== '' ? "Слаг: {$robotSlug}\r\n" : '');
    }
    $text .= "Задача:\r\n{$message}\r\n\r\n"
        . ($email !== ''
            ? "Ответьте на это письмо — уйдёт на почту клиента.\r\n\r\n"
            : "Почты в заявке нет, только контакт выше.\r\n\r\n")
        . "— AM QuantLab, {$site}\r\n";

    $html = quantlab_mail_layout([
        'eyebrow' => $robotTitle !== '' ? 'ЗАКАЗ РОБОТА' : 'НОВАЯ ЗАЯВКА',
        'title' => $robotTitle !== '' ? ('Заказ: ' . $robotTitle) : ('Заявка от ' . $name),
        'preheader' => $robotTitle !== ''
            ? ('Заказ робота ' . $robotTitle . ' с amquantlab.ru')
            : ('Новая заявка с amquantlab.ru от ' . $name),
        'intro' => $robotTitle !== ''
            ? 'На amquantlab.ru оформили готового робота.'
            : 'На amquantlab.ru оставили заявку.',
        'rows' => [
            'Дата' => $when,
            'Имя' => $name,
            'Контакт' => $contact,
            'Почта' => ($email !== '' && $email !== $contact) ? $email : '',
            'Рынок' => $market,
            'Робот' => $robotTitle,
            'Цена' => $robotPrice,
            'Слаг' => $robotSlug,
            'Задача' => $message,
        ],
        'note' => $email !== ''
            ? 'Ответьте на это письмо — уйдёт на почту клиента.'
            : 'Почты в заявке нет, только контакт выше.',
        'cta_href' => $site . '/admin/leads.php',
        'cta_label' => 'Открыть заявки',
    ]);

    quantlab_mail_send($subject, $text, $html, $email !== '' ? $email : null);
}

function quantlab_lead_ack_mail(array $lead): bool
{
    $email = quantlab_lead_email($lead);
    if ($email === '') {
        return false;
    }
    $name = trim((string) ($lead['name'] ?? ''));
    $hello = $name !== '' ? ('Здравствуйте, ' . $name . '.') : 'Здравствуйте.';
    $robotTitle = trim((string) ($lead['robot_title'] ?? ''));
    $site = function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru';
    $from = quantlab_env('SMTP_FROM', quantlab_env('SMTP_USER'));
    $subject = $robotTitle !== ''
        ? ('Заявка получена: ' . $robotTitle)
        : 'Заявка получена — AM QuantLab';
    $text = $hello . "\r\n\r\n"
        . ($robotTitle !== ''
            ? "Заявка на робота «{$robotTitle}» с amquantlab.ru дошла. Напишем на эту почту и уточним подключение.\r\n\r\n"
            : "Заявка с amquantlab.ru дошла. Напишем на эту почту и уточним задачу.\r\n\r\n")
        . "Если письмо пришло не вам — просто не отвечайте.\r\n\r\n"
        . "— AM QuantLab\r\n"
        . $site . "\r\n"
        . $from . "\r\n";
    $html = quantlab_mail_layout([
        'eyebrow' => 'ЗАЯВКА ПРИНЯТА',
        'title' => $robotTitle !== '' ? ('Заявка на «' . $robotTitle . '» получена') : 'Заявка получена',
        'preheader' => 'AM QuantLab получил заявку и напишет на эту почту.',
        'intro' => $hello,
        'body' => $robotTitle !== ''
            ? "Заявка на робота «{$robotTitle}» с amquantlab.ru дошла. Напишем на эту почту и уточним подключение."
            : 'Заявка с amquantlab.ru дошла. Напишем на эту почту и уточним задачу.',
        'note' => 'Если письмо пришло не вам — просто не отвечайте.',
        'cta_href' => $site,
        'cta_label' => 'Открыть AM QuantLab',
    ]);
    quantlab_mail_send($subject, $text, $html, $from, [$email]);
    return true;
}

function quantlab_lead_reply_mail(array $lead, string $body, string $subject = ''): array
{
    $email = quantlab_lead_email($lead);
    if ($email === '') {
        throw new InvalidArgumentException('В заявке нет почты — ответить письмом нельзя');
    }
    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('Напишите текст ответа');
    }
    $from = quantlab_env('SMTP_FROM', quantlab_env('SMTP_USER', 'info@amquantlab.ru'));
    $site = function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru';
    $name = trim((string) ($lead['name'] ?? ''));
    if ($subject === '') {
        $robotTitle = trim((string) ($lead['robot_title'] ?? ''));
        $subject = $robotTitle !== ''
            ? ('AM QuantLab: по заявке «' . $robotTitle . '»')
            : 'AM QuantLab: по вашей заявке';
    }
    $text = $body;
    if (!str_contains($body, 'AM QuantLab')) {
        $text .= "\r\n\r\n— AM QuantLab\r\n" . $from . "\r\n" . $site . "\r\n";
    }
    $htmlTitle = trim((string) ($lead['robot_title'] ?? ''));
    $html = quantlab_mail_layout([
        'eyebrow' => 'ОТВЕТ КОМАНДЫ',
        'title' => $htmlTitle !== '' ? ('По заявке «' . $htmlTitle . '»') : 'Ответ по вашей заявке',
        'preheader' => 'Ответ AM QuantLab по вашей заявке',
        'intro' => $name !== '' ? ('Здравствуйте, ' . $name . '.') : 'Здравствуйте.',
        'body' => $body,
        'cta_href' => $site,
        'cta_label' => 'Открыть AM QuantLab',
    ]);
    $id = (int) ($lead['id'] ?? 0);
    try {
        quantlab_mail_send($subject, $text, $html, $from, [$email]);
    } catch (Throwable $e) {
        if (function_exists('quantlab_lead_mark_replied')) {
            quantlab_lead_mark_replied($id, $email, $subject, false, $e->getMessage());
        }
        throw $e;
    }
    if (function_exists('quantlab_lead_mark_replied')) {
        quantlab_lead_mark_replied($id, $email, $subject, true, '');
    }
    return ['ok' => true, 'to' => $email, 'from' => $from, 'subject' => $subject, 'name' => $name];
}
