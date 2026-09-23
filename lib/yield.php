<?php

function quantlab_yield_token(): string
{
    $fromEnv = trim(quantlab_env('YIELD_API_TOKEN'));
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    $path = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'yield-token.txt';
    if (is_file($path)) {
        $saved = trim((string) file_get_contents($path));
        if ($saved !== '') {
            return $saved;
        }
    }
    $token = bin2hex(random_bytes(16));
    file_put_contents($path, $token, LOCK_EX);
    return $token;
}

function quantlab_yield_token_from_request(): string
{
    $candidates = [
        (string) ($_GET['token'] ?? ''),
        (string) ($_POST['token'] ?? ''),
        (string) ($_SERVER['HTTP_X_YIELD_TOKEN'] ?? ''),
        (string) ($_SERVER['HTTP_X_API_KEY'] ?? ''),
    ];
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'bearer ') === 0) {
        $candidates[] = trim(substr($auth, 7));
    }
    foreach ($candidates as $item) {
        $item = trim($item);
        if ($item !== '') {
            return $item;
        }
    }
    return '';
}

function quantlab_yield_token_ok(?string $given = null): bool
{
    $token = quantlab_yield_token();
    $given = $given !== null ? trim($given) : quantlab_yield_token_from_request();
    return $token !== '' && $given !== '' && hash_equals($token, $given);
}

function quantlab_yield_resolve_write(?string $given = null): array
{
    $given = $given !== null ? trim($given) : quantlab_yield_token_from_request();
    if ($given === '') {
        return [];
    }
    if (quantlab_yield_token_ok($given)) {
        return [
            'ok' => true,
            'slug' => quantlab_yield_slug(),
            'master' => true,
        ];
    }
    if (function_exists('quantlab_strategy_by_yield_token')) {
        $row = quantlab_strategy_by_yield_token($given);
        if ($row) {
            return [
                'ok' => true,
                'slug' => (string) $row['slug'],
                'master' => false,
            ];
        }
    }
    return [];
}

function quantlab_yield_period(array $series, int $days): float
{
    if ($series === []) {
        return 0.0;
    }
    $last = (float) ($series[count($series) - 1]['value'] ?? $series[count($series) - 1]['returnPercent'] ?? 0);
    $end = substr((string) ($series[count($series) - 1]['date'] ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return $last;
    }
    $from = (new DateTimeImmutable($end . ' 00:00:00', new DateTimeZone('Europe/Moscow')))
        ->modify('-' . $days . ' days')
        ->format('Y-m-d');
    $base = null;
    foreach ($series as $point) {
        $day = substr((string) ($point['date'] ?? ''), 0, 10);
        if ($day !== '' && $day <= $from) {
            $base = (float) ($point['value'] ?? $point['returnPercent'] ?? 0);
        }
    }
    if ($base === null) {
        $base = (float) ($series[0]['value'] ?? $series[0]['returnPercent'] ?? 0);
    }
    return $last - $base;
}

function quantlab_yield_slug(?string $raw = null): string
{
    $raw = $raw !== null ? $raw : (string) ($_GET['slug'] ?? $_POST['slug'] ?? 'main');
    $slug = strtolower(trim($raw));
    $slug = preg_replace('/[^a-z0-9-]+/', '', $slug) ?? '';
    return $slug !== '' ? $slug : 'main';
}

function quantlab_yield_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));
}

function quantlab_yield_num($value): float
{
    if (is_bool($value)) {
        return $value ? 1.0 : 0.0;
    }
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $text = trim((string) $value);
    $text = str_replace([' ', ','], ['', '.'], $text);
    $text = preg_replace('/[^0-9.\-]+/', '', $text) ?? '';
    return is_numeric($text) ? (float) $text : 0.0;
}

function quantlab_yield_bool($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $text = strtolower(trim((string) $value));
    return in_array($text, ['1', 'true', 'yes', 'on', 'running'], true) ? 1 : 0;
}

function quantlab_yield_date($value, DateTimeImmutable $fallback): string
{
    $text = trim((string) $value);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $match)) {
        return $match[1];
    }
    if ($text !== '') {
        $ts = strtotime($text);
        if ($ts) {
            return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d');
        }
    }
    return $fallback->format('Y-m-d');
}

function quantlab_yield_file(string $slug): string
{
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'yield';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . $slug . '.json';
}

function quantlab_yield_empty(string $slug): array
{
    return [
        'slug' => $slug,
        'date' => '',
        'time' => '',
        'returnPercent' => 0.0,
        'equity' => 0.0,
        'balance' => 0.0,
        'realizedPnl' => 0.0,
        'running' => 0,
        'updated_at' => '',
        'points' => [],
    ];
}

function quantlab_yield_load(string $slug): array
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $st = $pdo->prepare('SELECT * FROM yield_feeds WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $feed = $st->fetch(PDO::FETCH_ASSOC);
        if (!$feed) {
            return quantlab_yield_empty($slug);
        }
        $pts = $pdo->prepare('SELECT day, equity, balance, return_percent FROM yield_points WHERE slug = ? ORDER BY day ASC');
        $pts->execute([$slug]);
        $points = [];
        foreach ($pts->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $points[] = [
                'date' => (string) $row['day'],
                'equity' => (float) $row['equity'],
                'balance' => (float) $row['balance'],
                'returnPercent' => (float) $row['return_percent'],
            ];
        }
        return [
            'slug' => $slug,
            'date' => (string) ($feed['day'] ?? ''),
            'time' => (string) ($feed['sent_at'] ?? ''),
            'returnPercent' => (float) ($feed['return_percent'] ?? 0),
            'equity' => (float) ($feed['equity'] ?? 0),
            'balance' => (float) ($feed['balance'] ?? 0),
            'realizedPnl' => (float) ($feed['realized_pnl'] ?? 0),
            'running' => (int) ($feed['running'] ?? 0),
            'updated_at' => (string) ($feed['updated_at'] ?? ''),
            'points' => $points,
        ];
    }
    $path = quantlab_yield_file($slug);
    if (!is_file($path)) {
        return quantlab_yield_empty($slug);
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? array_merge(quantlab_yield_empty($slug), $data) : quantlab_yield_empty($slug);
}

function quantlab_yield_pick(array $src, array $keys, $default = null)
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $src) && $src[$key] !== '' && $src[$key] !== null) {
            return $src[$key];
        }
    }
    return $default;
}

function quantlab_yield_normalize_points($raw, DateTimeImmutable $now): array
{
    if (!is_array($raw)) {
        return [];
    }
    $isList = array_keys($raw) === range(0, count($raw) - 1);
    $rows = $isList ? $raw : [];
    if (!$isList && isset($raw['date'])) {
        $rows = [$raw];
    }
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = quantlab_yield_date(quantlab_yield_pick($row, ['date', 'day', 't']), $now);
        $equity = quantlab_yield_num(quantlab_yield_pick($row, ['equity', 'balance', 'value_abs'], 0));
        $balance = quantlab_yield_num(quantlab_yield_pick($row, ['balance', 'equity'], $equity));
        $ret = quantlab_yield_pick($row, ['returnPercent', 'return_percent', 'value', 'pnlPercent']);
        $out[$date] = [
            'date' => $date,
            'equity' => $equity,
            'balance' => $balance,
            'returnPercent' => $ret === null ? null : quantlab_yield_num($ret),
        ];
    }
    ksort($out);
    $points = array_values($out);
    $firstEq = 0.0;
    foreach ($points as $row) {
        if ($row['equity'] > 0) {
            $firstEq = $row['equity'];
            break;
        }
    }
    foreach ($points as $i => $row) {
        if ($row['returnPercent'] === null) {
            $points[$i]['returnPercent'] = $firstEq > 0
                ? (($row['equity'] - $firstEq) / $firstEq) * 100
                : 0.0;
        }
    }
    return $points;
}

function quantlab_yield_parse_payload(array $src): array
{
    $now = quantlab_yield_now();
    $pointsRaw = quantlab_yield_pick($src, ['points', 'series', 'equitySeries', 'days'], []);
    $points = quantlab_yield_normalize_points($pointsRaw, $now);
    $last = $points ? $points[count($points) - 1] : null;
    $equity = quantlab_yield_num(quantlab_yield_pick($src, ['equity'], $last['equity'] ?? 0));
    $balance = quantlab_yield_num(quantlab_yield_pick($src, ['balance'], $last['balance'] ?? $equity));
    $ret = quantlab_yield_pick($src, ['returnPercent', 'return_percent']);
    if ($ret === null) {
        $first = $points[0]['equity'] ?? 0;
        $ret = $first > 0 ? (($equity - $first) / $first) * 100 : ($last['returnPercent'] ?? 0);
    }
    $date = quantlab_yield_date(quantlab_yield_pick($src, ['date', 'day'], $last['date'] ?? $now->format('Y-m-d')), $now);
    if (!$points) {
        $points = [[
            'date' => $date,
            'equity' => $equity,
            'balance' => $balance,
            'returnPercent' => (float) $ret,
        ]];
    } else {
        $found = false;
        foreach ($points as $i => $row) {
            if ($row['date'] === $date) {
                $points[$i]['equity'] = $equity ?: $row['equity'];
                $points[$i]['balance'] = $balance ?: $row['balance'];
                $points[$i]['returnPercent'] = (float) $ret;
                $found = true;
            }
        }
        if (!$found) {
            $points[] = [
                'date' => $date,
                'equity' => $equity,
                'balance' => $balance,
                'returnPercent' => (float) $ret,
            ];
            usort($points, static fn ($a, $b) => strcmp($a['date'], $b['date']));
        }
    }
    return [
        'date' => $date,
        'time' => $now->format('c'),
        'returnPercent' => (float) $ret,
        'equity' => $equity,
        'balance' => $balance,
        'realizedPnl' => quantlab_yield_num(quantlab_yield_pick($src, ['realizedPnl', 'realized_pnl'], 0)),
        'running' => quantlab_yield_bool(quantlab_yield_pick($src, ['running'], 1)),
        'points' => $points,
    ];
}

function quantlab_yield_save(string $slug, array $payload, array $raw = []): array
{
    $now = quantlab_yield_now();
    $row = [
        'slug' => $slug,
        'date' => (string) $payload['date'],
        'time' => (string) $payload['time'],
        'returnPercent' => (float) $payload['returnPercent'],
        'equity' => (float) $payload['equity'],
        'balance' => (float) $payload['balance'],
        'realizedPnl' => (float) $payload['realizedPnl'],
        'running' => (int) $payload['running'],
        'updated_at' => $now->format('c'),
        'points' => $payload['points'],
    ];
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare(
                'INSERT INTO yield_feeds
                    (slug, day, sent_at, return_percent, equity, balance, realized_pnl, running, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    day = VALUES(day),
                    sent_at = VALUES(sent_at),
                    return_percent = VALUES(return_percent),
                    equity = VALUES(equity),
                    balance = VALUES(balance),
                    realized_pnl = VALUES(realized_pnl),
                    running = VALUES(running),
                    updated_at = VALUES(updated_at)'
            );
            $up->execute([
                $slug,
                $row['date'],
                $now->format('Y-m-d H:i:s'),
                $row['returnPercent'],
                $row['equity'],
                $row['balance'],
                $row['realizedPnl'],
                $row['running'],
                $now->format('Y-m-d H:i:s'),
            ]);
            $pdo->prepare('DELETE FROM yield_points WHERE slug = ?')->execute([$slug]);
            $ins = $pdo->prepare(
                'INSERT INTO yield_points (slug, day, equity, balance, return_percent)
                 VALUES (?,?,?,?,?)'
            );
            foreach ($row['points'] as $point) {
                $ins->execute([
                    $slug,
                    $point['date'],
                    $point['equity'],
                    $point['balance'],
                    $point['returnPercent'],
                ]);
            }
            $snap = $pdo->prepare(
                'INSERT INTO yield_snapshots
                    (slug, day, sent_at, return_percent, equity, balance, realized_pnl, running, payload)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $snap->execute([
                $slug,
                $row['date'],
                $now->format('Y-m-d H:i:s'),
                $row['returnPercent'],
                $row['equity'],
                $row['balance'],
                $row['realizedPnl'],
                $row['running'],
                json_encode($raw !== [] ? $raw : $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    file_put_contents(
        quantlab_yield_file($slug),
        json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    return $row;
}

function quantlab_yield_public(array $row): array
{
    $series = [];
    foreach ($row['points'] as $point) {
        $series[] = [
            'date' => $point['date'],
            'value' => (float) $point['returnPercent'],
            'returnPercent' => (float) $point['returnPercent'],
            'equity' => (float) $point['equity'],
            'balance' => (float) ($point['balance'] ?? $point['equity']),
        ];
    }
    $lifetime = $series !== []
        ? (float) ($series[count($series) - 1]['value'] ?? 0)
        : (float) $row['returnPercent'];
    $out = [
        'ok' => true,
        'slug' => $row['slug'],
        'date' => $row['date'],
        'time' => $row['time'],
        'returnPercent' => (float) $row['returnPercent'],
        'equity' => (float) $row['equity'],
        'balance' => (float) $row['balance'],
        'realizedPnl' => (float) $row['realizedPnl'],
        'running' => (bool) $row['running'],
        'updated_at' => $row['updated_at'],
        'points' => $series,
        'series' => $series,
    ];
    $strategy = function_exists('quantlab_strategy_load') ? quantlab_strategy_load((string) $row['slug']) : null;
    if ($strategy) {
        $out['strategy'] = [
            'title' => (string) $strategy['title'],
            'instrument' => (string) ($strategy['instrument'] ?? ''),
            'venue' => (string) ($strategy['venue'] ?? ''),
            'profitLifetime' => $lifetime,
            'profit90Days' => quantlab_yield_period($series, 90),
            'profit30Days' => quantlab_yield_period($series, 30),
            'profit7Days' => quantlab_yield_period($series, 7),
            'equity' => (float) $row['equity'],
            'balance' => (float) $row['balance'],
            'running' => (bool) $row['running'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
    return $out;
}

function quantlab_yield_read_body(): array
{
    $raw = (string) file_get_contents('php://input');
    $raw = trim($raw);
    if ($raw === '') {
        return $_POST ?: [];
    }
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    $pairs = [];
    parse_str($raw, $pairs);
    return is_array($pairs) ? $pairs : [];
}

function quantlab_yield_post_url(string $slug = 'main', ?string $token = null): string
{
    $site = rtrim(function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru', '/');
    $key = $token !== null && $token !== '' ? $token : quantlab_yield_token();
    return $site . '/api/yield/?slug=' . rawurlencode($slug) . '&token=' . rawurlencode($key);
}

function quantlab_yield_get_url(string $slug = 'main'): string
{
    $site = rtrim(function_exists('quantlab_site_url') ? quantlab_site_url() : 'https://amquantlab.ru', '/');
    return $site . '/api/yield/?slug=' . rawurlencode($slug);
}

function quantlab_yield_feeds(): array
{
    $pdo = function_exists('quantlab_db') ? quantlab_db() : null;
    if ($pdo) {
        $rows = $pdo->query('SELECT slug, day, return_percent, equity, updated_at FROM yield_feeds ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
    $dir = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'yield';
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $out[] = [
            'slug' => (string) ($data['slug'] ?? basename($file, '.json')),
            'day' => (string) ($data['date'] ?? ''),
            'return_percent' => (float) ($data['returnPercent'] ?? 0),
            'equity' => (float) ($data['equity'] ?? 0),
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }
    return $out;
}
