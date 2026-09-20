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
    $email = trim((string) ($input['email'] ?? ''));
    if ($email !== '' && function_exists('quantlab_mail_is_email') && !quantlab_mail_is_email($email)) {
        throw new InvalidArgumentException('Укажите почту в формате name@mail.ru');
    }
    if ($email === '' && function_exists('quantlab_lead_email')) {
        $email = quantlab_lead_email(['email' => '', 'contact' => $contact]);
    }
    $market = trim((string) ($input['market'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $robotSlug = trim((string) ($input['robot_slug'] ?? ''));
    $robotTitle = '';
    $robotPrice = '';
    if ($robotSlug !== '') {
        if (!function_exists('quantlab_ready_load')) {
            throw new InvalidArgumentException('Каталог роботов недоступен');
        }
        $robot = quantlab_ready_load($robotSlug);
        if (!$robot || ($robot['status'] ?? '') !== 'visible') {
            throw new InvalidArgumentException('Этот робот сейчас недоступен');
        }
        $robotTitle = (string) $robot['title'];
        $robotPrice = quantlab_ready_price_label((string) $robot['price']);
        $market = 'ready';
        if ($message === '') {
            $message = 'Оформление готового робота «' . $robotTitle . '»';
        }
    }
    if ($name === '' || $contact === '') {
        throw new InvalidArgumentException('Заполните имя и контакт');
    }
    if ($robotSlug === '' && $message === '') {
        throw new InvalidArgumentException('Заполните имя, контакт и задачу');
    }
    $lead = [
        'name' => $name,
        'contact' => $contact,
        'email' => $email,
        'market' => $market !== '' ? $market : 'finam',
        'message' => $message,
        'robot_slug' => $robotSlug,
        'robot_title' => $robotTitle,
        'robot_price' => $robotPrice,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'created_at' => date('c'),
    ];

    $pdo = quantlab_db();
    if ($pdo) {
        if (quantlab_lead_rate_limited_db($pdo, $lead['ip'])) {
            throw new RuntimeException('Слишком много заявок. Попробуйте позже.');
        }
        try {
            $st = $pdo->prepare(
                'INSERT INTO leads (name, contact, email, market, message, ip, created_at, robot_slug, robot_title, robot_price)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $lead['name'],
                $lead['contact'],
                $lead['email'] !== '' ? $lead['email'] : null,
                $lead['market'],
                $lead['message'],
                $lead['ip'],
                date('Y-m-d H:i:s'),
                $lead['robot_slug'] !== '' ? $lead['robot_slug'] : null,
                $lead['robot_title'] !== '' ? $lead['robot_title'] : null,
                $lead['robot_price'] !== '' ? $lead['robot_price'] : null,
            ]);
        } catch (Throwable $e) {
            $body = $lead['message'];
            if ($lead['robot_title'] !== '') {
                $body = 'Робот: ' . $lead['robot_title'] . "\nЦена: " . $lead['robot_price'] . "\n\n" . $body;
            }
            $st = $pdo->prepare(
                'INSERT INTO leads (name, contact, market, message, ip, created_at) VALUES (?,?,?,?,?,?)'
            );
            $st->execute([
                $lead['name'],
                $lead['contact'],
                $lead['market'],
                $body,
                $lead['ip'],
                date('Y-m-d H:i:s'),
            ]);
            $lead['message'] = $body;
        }
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

function quantlab_lead_find_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || !function_exists('quantlab_mail_is_email') || !quantlab_mail_is_email($email)) {
        return null;
    }
    $best = null;
    $bestId = 0;
    foreach (quantlab_leads_all() as $lead) {
        $got = function_exists('quantlab_lead_email') ? strtolower(quantlab_lead_email($lead)) : '';
        if ($got !== $email) {
            continue;
        }
        $id = (int) ($lead['id'] ?? 0);
        if ($id >= $bestId) {
            $best = $lead;
            $bestId = $id;
        }
    }
    return $best;
}

function quantlab_lead_messages_path(): string
{
    return quantlab_data_dir() . DIRECTORY_SEPARATOR . 'lead-messages.json';
}

function quantlab_lead_messages_all(): array
{
    $path = quantlab_lead_messages_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function quantlab_lead_messages_write(array $data): void
{
    file_put_contents(
        quantlab_lead_messages_path(),
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_lead_thread_seed(array $lead): void
{
    $id = (int) ($lead['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $email = function_exists('quantlab_lead_email') ? quantlab_lead_email($lead) : '';
    quantlab_lead_thread_add($id, [
        'dir' => 'in',
        'kind' => 'lead',
        'from' => $email !== '' ? $email : (string) ($lead['contact'] ?? ''),
        'to' => function_exists('quantlab_site_email') ? quantlab_site_email() : 'info@amquantlab.ru',
        'subject' => !empty($lead['robot_title'])
            ? ('Заявка: ' . $lead['robot_title'])
            : 'Заявка с сайта',
        'body' => (string) ($lead['message'] ?? ''),
        'at' => (string) ($lead['created_at'] ?? date('c')),
        'message_id' => 'lead-' . $id,
        'read' => true,
    ]);
}

function quantlab_lead_thread(int $id, ?array $lead = null): array
{
    $all = quantlab_lead_messages_all();
    $rows = $all[(string) $id] ?? [];
    $rows = is_array($rows) ? $rows : [];
    if ($rows === [] && $lead) {
        $email = function_exists('quantlab_lead_email') ? quantlab_lead_email($lead) : '';
        $rows[] = [
            'id' => 'lead-' . $id,
            'dir' => 'in',
            'kind' => 'lead',
            'from' => $email !== '' ? $email : (string) ($lead['contact'] ?? ''),
            'to' => '',
            'subject' => !empty($lead['robot_title'])
                ? ('Заявка: ' . $lead['robot_title'])
                : 'Заявка с сайта',
            'body' => (string) ($lead['message'] ?? ''),
            'at' => (string) ($lead['created_at'] ?? ''),
            'message_id' => 'lead-' . $id,
            'read' => true,
        ];
    }
    usort($rows, static function ($a, $b) {
        return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
    });
    return array_values($rows);
}

function quantlab_lead_thread_add(int $id, array $msg): bool
{
    if ($id <= 0) {
        return false;
    }
    $mid = trim((string) ($msg['message_id'] ?? ''));
    $all = quantlab_lead_messages_all();
    if ($mid !== '') {
        foreach ($all as $rows) {
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (strcasecmp((string) ($row['message_id'] ?? ''), $mid) === 0) {
                    return false;
                }
            }
        }
    }
    $key = (string) $id;
    if (!isset($all[$key]) || !is_array($all[$key])) {
        $all[$key] = [];
    }
    $all[$key][] = [
        'id' => $mid !== '' ? $mid : ('ql-' . bin2hex(random_bytes(6))),
        'dir' => (($msg['dir'] ?? 'in') === 'out') ? 'out' : 'in',
        'kind' => (string) ($msg['kind'] ?? 'reply'),
        'from' => (string) ($msg['from'] ?? ''),
        'to' => (string) ($msg['to'] ?? ''),
        'subject' => (string) ($msg['subject'] ?? ''),
        'body' => (string) ($msg['body'] ?? ''),
        'at' => (string) ($msg['at'] ?? date('c')),
        'message_id' => $mid,
        'in_reply_to' => (string) ($msg['in_reply_to'] ?? ''),
        'read' => !empty($msg['read']) || (($msg['dir'] ?? '') === 'out'),
        'imap_uid' => (int) ($msg['imap_uid'] ?? 0),
    ];
    quantlab_lead_messages_write($all);
    return true;
}

function quantlab_lead_thread_mark_read(int $id): void
{
    if ($id <= 0) {
        return;
    }
    $all = quantlab_lead_messages_all();
    $key = (string) $id;
    if (empty($all[$key]) || !is_array($all[$key])) {
        return;
    }
    foreach ($all[$key] as &$row) {
        $row['read'] = true;
    }
    unset($row);
    quantlab_lead_messages_write($all);
}

function quantlab_lead_thread_unread(int $id): int
{
    $n = 0;
    foreach (quantlab_lead_thread($id) as $row) {
        if (($row['dir'] ?? '') === 'in' && empty($row['read']) && ($row['kind'] ?? '') !== 'lead') {
            $n++;
        }
    }
    return $n;
}

function quantlab_text_clip(string $text, int $len = 110): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $len) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $len - 1)) . '…';
    }
    if (strlen($text) <= $len) {
        return $text;
    }
    return rtrim(substr($text, 0, $len - 1)) . '…';
}

function quantlab_lead_thread_preview(array $thread): array
{
    if ($thread === []) {
        return ['text' => '', 'dir' => '', 'kind' => '', 'label' => ''];
    }
    $last = $thread[count($thread) - 1];
    $kind = (string) ($last['kind'] ?? '');
    $dir = (string) ($last['dir'] ?? 'in');
    $label = $kind === 'lead' ? 'Заявка' : ($dir === 'out' ? 'Вы' : 'Клиент');
    return [
        'text' => quantlab_text_clip((string) ($last['body'] ?? ''), 96),
        'dir' => $dir,
        'kind' => $kind,
        'label' => $label,
    ];
}

function quantlab_lead_thread_last_ref(int $id): string
{
    $rows = quantlab_lead_thread($id);
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        $mid = trim((string) ($rows[$i]['message_id'] ?? ''));
        if ($mid !== '' && ($rows[$i]['dir'] ?? '') === 'in') {
            return $mid;
        }
    }
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        $mid = trim((string) ($rows[$i]['message_id'] ?? ''));
        if ($mid !== '') {
            return $mid;
        }
    }
    return '';
}

function quantlab_lead_notify(array $lead): void
{
    if (function_exists('quantlab_lead_thread_seed')) {
        quantlab_lead_thread_seed($lead);
    }
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
        return;
    }
    try {
        if (function_exists('quantlab_lead_ack_mail')) {
            quantlab_lead_ack_mail($lead);
        }
    } catch (Throwable $e) {
        if (function_exists('quantlab_mail_status')) {
            quantlab_mail_status(true, 'Заявка вам ушла, клиенту нет: ' . $e->getMessage());
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
        'ready' => 'Готовый робот',
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

function quantlab_lead_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $pdo = quantlab_db();
    if ($pdo) {
        $st = $pdo->prepare('SELECT * FROM leads WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $row['created_at'] = quantlab_dt_iso($row['created_at'] ?? null);
        return $row;
    }
    foreach (quantlab_leads_all() as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

function quantlab_lead_replies_path(): string
{
    return quantlab_data_dir() . DIRECTORY_SEPARATOR . 'lead-replies.json';
}

function quantlab_lead_replies_map(): array
{
    $path = quantlab_lead_replies_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function quantlab_lead_last_reply(int $id): array
{
    $map = quantlab_lead_replies_map();
    $row = $map[(string) $id] ?? null;
    return is_array($row) ? $row : [];
}

function quantlab_lead_mark_replied(int $id, string $to, string $subject, bool $ok = true, string $error = ''): void
{
    if ($id <= 0) {
        return;
    }
    $map = quantlab_lead_replies_map();
    $map[(string) $id] = [
        'ok' => $ok,
        'to' => $to,
        'subject' => $subject,
        'error' => $error,
        'at' => date('c'),
    ];
    file_put_contents(
        quantlab_lead_replies_path(),
        json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function quantlab_lead_reply_status(array $last, int $unread = 0, bool $hasIn = false): array
{
    if ($unread > 0) {
        return [
            'key' => 'in',
            'label' => $unread === 1 ? 'Написал' : ('Написал · ' . $unread),
            'class' => 'badge badge-in',
            'hint' => 'Новое письмо в диалоге',
        ];
    }
    if ($hasIn) {
        return [
            'key' => 'dialog',
            'label' => 'Диалог',
            'class' => 'badge badge-ok',
            'hint' => '',
        ];
    }
    if ($last === []) {
        return [
            'key' => 'none',
            'label' => 'Не отправляли',
            'class' => 'badge',
            'hint' => '',
        ];
    }
    $at = '';
    if (!empty($last['at'])) {
        $ts = strtotime((string) $last['at']);
        $at = $ts ? date('d.m.Y H:i', $ts) : (string) $last['at'];
    }
    $ok = array_key_exists('ok', $last) ? !empty($last['ok']) : true;
    if ($ok) {
        return [
            'key' => 'ok',
            'label' => 'Отправлено',
            'class' => 'badge badge-ok',
            'hint' => $at !== '' ? $at : '',
        ];
    }
    $error = trim((string) ($last['error'] ?? ''));
    return [
        'key' => 'err',
        'label' => 'Не ушло',
        'class' => 'badge badge-warn',
        'hint' => $error !== '' ? $error : $at,
    ];
}
