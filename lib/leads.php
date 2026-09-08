<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'cache.php';

function quantlab_leads_file(): string
{
    return quantlab_data_dir() . DIRECTORY_SEPARATOR . 'leads.json';
}

function quantlab_lead_save(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $contact = trim((string) ($input['contact'] ?? ''));
    $market = trim((string) ($input['market'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    if ($name === '' || $contact === '' || $message === '') {
        throw new InvalidArgumentException('Заполните имя, контакт и задачу');
    }
    $lead = [
        'name' => $name,
        'contact' => $contact,
        'market' => $market !== '' ? $market : 'finam',
        'message' => $message,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'created_at' => date('c'),
    ];

    $pdo = quantlab_db();
    if ($pdo) {
        if (quantlab_lead_rate_limited_db($pdo, $lead['ip'])) {
            throw new RuntimeException('Слишком много заявок. Попробуйте позже.');
        }
        $st = $pdo->prepare(
            'INSERT INTO leads (name, contact, market, message, ip, created_at) VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            $lead['name'],
            $lead['contact'],
            $lead['market'],
            $lead['message'],
            $lead['ip'],
            date('Y-m-d H:i:s'),
        ]);
        $lead['id'] = (int) $pdo->lastInsertId();
        quantlab_lead_notify($lead);
        return $lead;
    }

    $rows = [];
    $path = quantlab_leads_file();
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        $rows = is_array($decoded) ? $decoded : [];
    }
    if (quantlab_lead_rate_limited_file($rows, $lead['ip'])) {
        throw new RuntimeException('Слишком много заявок. Попробуйте позже.');
    }
    $lead['id'] = count($rows) + 1;
    $rows[] = $lead;
    file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    quantlab_lead_notify($lead);
    return $lead;
}

function quantlab_lead_notify(array $lead): void
{
    if (!function_exists('quantlab_mail_enabled') || !quantlab_mail_enabled()) {
        if (function_exists('quantlab_mail_status')) {
            quantlab_mail_status(false, 'SMTP не настроен: в .env пустой SMTP_PASSWORD для info@amquantlab.ru');
        }
        return;
    }
    try {
        quantlab_lead_mail($lead);
    } catch (Throwable $e) {
        if (function_exists('quantlab_mail_status')) {
            quantlab_mail_status(false, $e->getMessage());
        }
    }
}

function quantlab_lead_rate_limited_db(PDO $pdo, string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $st->execute([$ip]);
    return (int) $st->fetchColumn() >= 8;
}

function quantlab_lead_rate_limited_file(array $rows, string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    $since = time() - 3600;
    $n = 0;
    foreach ($rows as $row) {
        if (($row['ip'] ?? '') !== $ip) {
            continue;
        }
        $ts = strtotime((string) ($row['created_at'] ?? ''));
        if ($ts && $ts >= $since) {
            $n++;
        }
    }
    return $n >= 8;
}

function quantlab_lead_market_label(string $market): string
{
    $map = [
        'finam' => 'Финам / MOEX',
        'tinkoff' => 'Тинькофф Инвестиции',
        'bybit' => 'Bybit',
        'okx' => 'OKX',
        'binance' => 'Binance',
        'multi' => 'Несколько площадок',
        'fintech' => 'Сервис для финтех-продукта',
    ];
    return $map[$market] ?? $market;
}

function quantlab_leads_all(): array
{
    $pdo = quantlab_db();
    if ($pdo) {
        $rows = $pdo->query('SELECT * FROM leads ORDER BY id DESC')->fetchAll();
        return array_map(static function ($row) {
            $row['created_at'] = quantlab_dt_iso($row['created_at'] ?? null);
            return $row;
        }, $rows ?: []);
    }
    $path = quantlab_leads_file();
    if (!is_file($path)) {
        return [];
    }
    $rows = json_decode((string) file_get_contents($path), true);
    return is_array($rows) ? array_reverse($rows) : [];
}
