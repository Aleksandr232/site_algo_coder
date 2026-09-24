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
        'files' => quantlab_lead_files_public($msg['files'] ?? []),
        'at' => (string) ($msg['at'] ?? date('c')),
        'message_id' => $mid,
        'in_reply_to' => (string) ($msg['in_reply_to'] ?? ''),
        'read' => !empty($msg['read']) || (($msg['dir'] ?? '') === 'out'),
        'imap_uid' => (int) ($msg['imap_uid'] ?? 0),
        'notified' => !empty($msg['notified']) || (($msg['dir'] ?? '') === 'out'),
    ];
    quantlab_lead_messages_write($all);
    return true;
}

function quantlab_lead_thread_by_imap(int $id, int $uid): ?array
{
    if ($id <= 0 || $uid <= 0) {
        return null;
    }
    foreach (quantlab_lead_thread($id) as $row) {
        if ((int) ($row['imap_uid'] ?? 0) === $uid) {
            return $row;
        }
    }
    return null;
}

function quantlab_lead_thread_mark_notified(int $id, int $uid): void
{
    if ($id <= 0 || $uid <= 0) {
        return;
    }
    $all = quantlab_lead_messages_all();
    $key = (string) $id;
    if (empty($all[$key]) || !is_array($all[$key])) {
        return;
    }
    foreach ($all[$key] as $i => $row) {
        if ((int) ($row['imap_uid'] ?? 0) === $uid) {
            $all[$key][$i]['notified'] = true;
            quantlab_lead_messages_write($all);
            return;
        }
    }
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
        $row['notified'] = true;
    }
    unset($row);
    quantlab_lead_messages_write($all);
}

function quantlab_lead_pending_inbound(): array
{
    $out = [];
    foreach (quantlab_leads_all() as $lead) {
        $id = (int) ($lead['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        foreach (quantlab_lead_thread($id, $lead) as $row) {
            if (($row['dir'] ?? '') !== 'in' || ($row['kind'] ?? '') !== 'reply') {
                continue;
            }
            if (!empty($row['read']) || !empty($row['notified'])) {
                continue;
            }
            $out[] = [
                'lead' => $lead,
                'msg' => $row,
            ];
        }
    }
    usort($out, static function ($a, $b) {
        $ta = strtotime((string) ($a['msg']['at'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['msg']['at'] ?? '')) ?: 0;
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
        return ((int) ($a['msg']['imap_uid'] ?? 0)) <=> ((int) ($b['msg']['imap_uid'] ?? 0));
    });
    return $out;
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

function quantlab_lead_inbound_count(array $thread): int
{
    $n = 0;
    foreach ($thread as $row) {
        if (($row['dir'] ?? '') === 'in' && ($row['kind'] ?? '') === 'reply') {
            $n++;
        }
    }
    return $n;
}

function quantlab_ru_count(int $n, string $one, string $few, string $many): string
{
    $n = abs($n);
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $n . ' ' . $one;
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $n . ' ' . $few;
    }
    return $n . ' ' . $many;
}

function quantlab_lead_delete(int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $found = false;
    $pdo = quantlab_db();
    if ($pdo) {
        $st = $pdo->prepare('DELETE FROM leads WHERE id = ?');
        $st->execute([$id]);
        $found = $st->rowCount() > 0;
    }
    $path = quantlab_leads_file();
    if (is_file($path)) {
        $rows = json_decode((string) file_get_contents($path), true);
        $rows = is_array($rows) ? $rows : [];
        $keep = [];
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $found = true;
                continue;
            }
            $keep[] = $row;
        }
        file_put_contents($path, json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
    $messages = quantlab_lead_messages_all();
    if (isset($messages[(string) $id])) {
        unset($messages[(string) $id]);
        quantlab_lead_messages_write($messages);
    }
    quantlab_lead_files_delete_lead($id);
    $replies = quantlab_lead_replies_map();
    if (isset($replies[(string) $id])) {
        unset($replies[(string) $id]);
        file_put_contents(
            quantlab_lead_replies_path(),
            json_encode($replies, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
    return $found;
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

function quantlab_lead_inbound_notify(array $lead, array $msg): bool
{
    if (!function_exists('quantlab_mail_enabled') || !quantlab_mail_enabled()) {
        return false;
    }
    if (!function_exists('quantlab_lead_inbound_mail')) {
        return false;
    }
    try {
        quantlab_lead_inbound_mail($lead, $msg);
        return true;
    } catch (Throwable $e) {
        if (function_exists('quantlab_mail_status')) {
            quantlab_mail_status(false, 'Клиент написал, но уведомление не ушло: ' . $e->getMessage());
        }
        return false;
    }
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

function quantlab_lead_file_exts(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
}

function quantlab_lead_file_ext(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
}

function quantlab_lead_file_mime(string $ext): string
{
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        default => 'application/octet-stream',
    };
}

function quantlab_lead_file_safe_name(string $name): string
{
    $name = str_replace(["\0", '/', '\\'], '', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'file';
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 120, 'UTF-8');
    } elseif (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }
    return $name;
}

function quantlab_lead_files_dir(int $leadId): string
{
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'lead-files' . DIRECTORY_SEPARATOR . $leadId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function quantlab_lead_file_path(int $leadId, string $fid): string
{
    if (!preg_match('/^[a-f0-9]{16}$/', $fid)) {
        return '';
    }
    return quantlab_lead_files_dir($leadId) . DIRECTORY_SEPARATOR . $fid;
}

function quantlab_lead_files_public(mixed $files): array
{
    if (!is_array($files)) {
        return [];
    }
    $out = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $id = (string) ($file['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            continue;
        }
        $name = quantlab_lead_file_safe_name((string) ($file['name'] ?? 'file'));
        $ext = quantlab_lead_file_ext($name);
        $out[] = [
            'id' => $id,
            'name' => $name,
            'mime' => quantlab_lead_file_mime($ext),
            'size' => max(0, (int) ($file['size'] ?? 0)),
        ];
        if (count($out) >= 5) {
            break;
        }
    }
    return $out;
}

function quantlab_lead_file_store(int $leadId, string $name, string $bytes): array
{
    $safe = quantlab_lead_file_safe_name($name);
    $ext = quantlab_lead_file_ext($safe);
    if (!in_array($ext, quantlab_lead_file_exts(), true)) {
        throw new InvalidArgumentException('Такой файл прикрепить нельзя: ' . $safe);
    }
    if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('Файл должен быть не пустым и не больше 8 МБ');
    }
    $id = bin2hex(random_bytes(8));
    $path = quantlab_lead_file_path($leadId, $id);
    if ($path === '' || file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Не удалось сохранить файл');
    }
    return [
        'id' => $id,
        'name' => $safe,
        'mime' => quantlab_lead_file_mime($ext),
        'size' => strlen($bytes),
    ];
}

function quantlab_lead_files_take_upload(int $leadId): array
{
    if ($leadId <= 0 || empty($_FILES['files']) || !is_array($_FILES['files']['name'] ?? null)) {
        return [];
    }
    $names = $_FILES['files']['name'];
    $tmp = $_FILES['files']['tmp_name'];
    $errs = $_FILES['files']['error'];
    $out = [];
    $count = 0;
    foreach ($names as $i => $name) {
        $err = (int) ($errs[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || (string) $name === '') {
            continue;
        }
        $count++;
        if ($count > 5) {
            quantlab_lead_files_discard($leadId, $out);
            throw new InvalidArgumentException('Не больше 5 файлов за одно сообщение');
        }
        if ($err !== UPLOAD_ERR_OK) {
            quantlab_lead_files_discard($leadId, $out);
            throw new InvalidArgumentException('Файл не загрузился. Проверьте размер, лимит 8 МБ.');
        }
        $path = (string) ($tmp[$i] ?? '');
        $bytes = is_file($path) ? (string) file_get_contents($path) : '';
        try {
            $out[] = quantlab_lead_file_store($leadId, (string) $name, $bytes);
        } catch (Throwable $e) {
            quantlab_lead_files_discard($leadId, $out);
            throw $e;
        }
    }
    return $out;
}

function quantlab_lead_files_discard(int $leadId, array $files): void
{
    foreach (quantlab_lead_files_public($files) as $file) {
        $path = quantlab_lead_file_path($leadId, (string) $file['id']);
        if ($path !== '' && is_file($path)) {
            unlink($path);
        }
    }
}

function quantlab_lead_files_delete_lead(int $leadId): void
{
    if ($leadId <= 0) {
        return;
    }
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'lead-files' . DIRECTORY_SEPARATOR . $leadId;
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir($dir);
}

function quantlab_lead_files_mail_parts(int $leadId, array $files): array
{
    $out = [];
    foreach (quantlab_lead_files_public($files) as $file) {
        $path = quantlab_lead_file_path($leadId, (string) $file['id']);
        if ($path === '' || !is_file($path)) {
            continue;
        }
        $out[] = [
            'name' => (string) $file['name'],
            'mime' => (string) $file['mime'],
            'path' => $path,
        ];
    }
    return $out;
}

function quantlab_lead_file_find(int $leadId, string $fid): ?array
{
    if ($leadId <= 0 || !preg_match('/^[a-f0-9]{16}$/', $fid)) {
        return null;
    }
    foreach (quantlab_lead_thread($leadId) as $row) {
        foreach (quantlab_lead_files_public($row['files'] ?? []) as $file) {
            if ($file['id'] === $fid && is_file(quantlab_lead_file_path($leadId, $fid))) {
                return $file;
            }
        }
    }
    return null;
}

function quantlab_lead_file_output(int $leadId, string $fid): void
{
    $file = quantlab_lead_file_find($leadId, $fid);
    $path = $file ? quantlab_lead_file_path($leadId, $fid) : '';
    if (!$file || $path === '' || !is_file($path)) {
        http_response_code(404);
        echo 'Файл не найден';
        return;
    }
    $mime = (string) $file['mime'];
    $inline = str_starts_with($mime, 'image/');
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . (string) filesize($path));
    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $file['name']) ?: 'file';
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"');
    readfile($path);
}
