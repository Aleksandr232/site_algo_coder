<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/cache.php';

function quantlab_num($value): float
{
    $n = (float) $value;
    return is_finite($n) ? $n : 0.0;
}

function quantlab_day_key($ms): string
{
    $ts = (int) floor(((float) $ms) / 1000);
    return gmdate('Y-m-d', $ts > 0 ? $ts : time());
}

function quantlab_bybit_get(string $path, array $params = []): array
{
    $key = quantlab_env('BYBIT_API_KEY');
    $secret = quantlab_env('BYBIT_API_SECRET');
    $base = rtrim(quantlab_env('BYBIT_BASE', 'https://api.bybit.com'), '/');
    if ($key === '' || $secret === '') {
        throw new RuntimeException('Bybit keys are missing');
    }

    ksort($params);
    $queryParts = [];
    foreach ($params as $name => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $queryParts[] = $name . '=' . rawurlencode((string) $value);
    }
    $query = implode('&', $queryParts);
    $timestamp = (string) (int) floor(microtime(true) * 1000);
    $recv = '10000';
    $sign = hash_hmac('sha256', $timestamp . $key . $recv . $query, $secret);
    $url = $base . $path . ($query !== '' ? '?' . $query : '');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'X-BAPI-API-KEY: ' . $key,
            'X-BAPI-TIMESTAMP: ' . $timestamp,
            'X-BAPI-SIGN: ' . $sign,
            'X-BAPI-RECV-WINDOW: ' . $recv,
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'Bybit request failed');
    }
    curl_close($ch);
    $json = json_decode($raw, true);
    if (!is_array($json) || (int) ($json['retCode'] ?? 1) !== 0) {
        $msg = is_array($json) ? ($json['retMsg'] ?? 'Bybit error') : 'Bybit error';
        $code = is_array($json) ? ($json['retCode'] ?? '?') : '?';
        throw new RuntimeException($msg . ' (' . $code . ')');
    }
    return $json['result'] ?? [];
}

function quantlab_pick_account(array $list): ?array
{
    foreach ($list as $item) {
        if (quantlab_num($item['totalEquity'] ?? 0) > 0) {
            return $item;
        }
    }
    foreach ($list as $item) {
        if (quantlab_num($item['totalWalletBalance'] ?? 0) > 0) {
            return $item;
        }
    }
    return $list[0] ?? null;
}

function quantlab_fetch_wallet(): ?array
{
    foreach (['UNIFIED', 'CONTRACT', 'SPOT'] as $type) {
        try {
            $result = quantlab_bybit_get('/v5/account/wallet-balance', ['accountType' => $type]);
            $account = quantlab_pick_account($result['list'] ?? []);
            if ($account) {
                return ['accountType' => $type, 'account' => $account];
            }
        } catch (Throwable $e) {
            if (!str_contains($e->getMessage(), '10001') && !str_contains($e->getMessage(), 'account')) {
                throw $e;
            }
        }
    }
    return null;
}

function quantlab_fetch_closed_pnl(): array
{
    $rows = [];
    $cursor = '';
    for ($i = 0; $i < 6; $i++) {
        $result = quantlab_bybit_get('/v5/position/closed-pnl', [
            'category' => 'linear',
            'symbol' => 'BTCUSDT',
            'limit' => 100,
            'cursor' => $cursor,
        ]);
        $list = $result['list'] ?? [];
        $rows = array_merge($rows, $list);
        $cursor = $result['nextPageCursor'] ?? '';
        if ($cursor === '' || count($list) < 100) {
            break;
        }
    }
    return $rows;
}

function quantlab_fetch_positions(): array
{
    try {
        $result = quantlab_bybit_get('/v5/position/list', [
            'category' => 'linear',
            'symbol' => 'BTCUSDT',
        ]);
        return $result['list'] ?? [];
    } catch (Throwable $e) {
        return [];
    }
}

function quantlab_daily_pnl(array $closed): array
{
    $days = [];
    foreach ($closed as $item) {
        $day = quantlab_day_key($item['updatedTime'] ?? $item['createdTime'] ?? 0);
        $days[$day] = ($days[$day] ?? 0) + quantlab_num($item['closedPnl'] ?? 0);
    }
    return $days;
}

function quantlab_build_equity(array $wallet, array $closed): array
{
    $equity = quantlab_num($wallet['totalEquity'] ?? $wallet['totalWalletBalance'] ?? 0);
    $unrealized = quantlab_num($wallet['totalPerpUPL'] ?? 0);
    $daily = quantlab_daily_pnl($closed);
    $days = array_keys($daily);
    sort($days);
    if (!$days) {
        $today = gmdate('Y-m-d');
        return [
            'equity' => $equity,
            'series' => [['date' => $today, 'value' => 0, 'balance' => $equity]],
        ];
    }

    $cursor = $equity - $unrealized;
    $backwards = [];
    for ($i = count($days) - 1; $i >= 0; $i--) {
        $day = $days[$i];
        $backwards[] = ['date' => $day, 'balance' => $cursor, 'pnl' => $daily[$day]];
        $cursor -= $daily[$day];
    }
    $backwards = array_reverse($backwards);
    $start = $backwards[0]['balance'] - ($daily[$backwards[0]['date']] ?? 0);
    $base = abs($start) > 1e-8 ? $start : ($equity ?: 1);
    $series = [];
    foreach ($backwards as $row) {
        $series[] = [
            'date' => $row['date'],
            'value' => (($row['balance'] - $base) / $base) * 100,
            'balance' => $row['balance'],
        ];
    }
    $today = gmdate('Y-m-d');
    $last = $series[count($series) - 1];
    if ($last['date'] !== $today) {
        $series[] = [
            'date' => $today,
            'value' => (($equity - $base) / $base) * 100,
            'balance' => $equity,
        ];
    } else {
        $series[count($series) - 1]['balance'] = $equity;
        $series[count($series) - 1]['value'] = (($equity - $base) / $base) * 100;
    }
    return ['equity' => $equity, 'series' => $series];
}

function quantlab_period_return(array $series, $days): float
{
    if (!$series) {
        return 0.0;
    }
    $last = $series[count($series) - 1]['value'];
    if ($days === 'all') {
        return $last;
    }
    $from = $series[max(0, count($series) - (int) $days)]['value'];
    return ((100 + $last) / (100 + $from) - 1) * 100;
}

function quantlab_snapshot_daily(float $equity): array
{
    $file = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'bybit-daily.json';
    $rows = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }
    $today = gmdate('Y-m-d');
    $found = false;
    foreach ($rows as &$row) {
        if (($row['date'] ?? '') === $today) {
            $row['balance'] = $equity;
            $found = true;
        }
    }
    unset($row);
    if (!$found) {
        $rows[] = ['date' => $today, 'balance' => $equity];
    }
    usort($rows, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    return $rows;
}

function quantlab_merge_snapshots(array $series, array $snapshots): array
{
    if (count($snapshots) < 2) {
        return $series;
    }
    $start = $snapshots[0]['balance'] ?: 1;
    $byDate = [];
    foreach ($series as $row) {
        $byDate[$row['date']] = $row;
    }
    foreach ($snapshots as $row) {
        $byDate[$row['date']] = [
            'date' => $row['date'],
            'value' => (($row['balance'] - $start) / $start) * 100,
            'balance' => $row['balance'],
        ];
    }
    ksort($byDate);
    return array_values($byDate);
}

function quantlab_summarize(array $wallet, array $positions, array $series): array
{
    $account = $wallet['account'] ?? [];
    $equity = quantlab_num($account['totalEquity'] ?? $account['totalWalletBalance'] ?? 0);
    $coin = ['coin' => 'USDT'];
    foreach (($account['coin'] ?? []) as $item) {
        if (($item['coin'] ?? '') === 'USDT' || ($item['coin'] ?? '') === 'BTC') {
            $coin = $item;
            break;
        }
    }
    $pos = ['side' => 'None', 'size' => 0, 'avgPrice' => 0, 'unrealisedPnl' => 0];
    foreach ($positions as $item) {
        if (quantlab_num($item['size'] ?? 0) > 0) {
            $pos = $item;
            break;
        }
    }
    if (!$positions && isset($positions[0])) {
        $pos = $positions[0];
    } elseif ($positions && quantlab_num($pos['size'] ?? 0) === 0) {
        $pos = $positions[0];
    }
    return [
        'title' => 'BTC Trend · Bybit · тест',
        'venue' => 'Bybit',
        'instrument' => 'BTCUSDT Perp',
        'accountType' => $wallet['accountType'] ?? 'UNIFIED',
        'createdAt' => $series[0]['date'] ?? gmdate('Y-m-d'),
        'equity' => $equity,
        'currency' => $coin['coin'] ?? 'USDT',
        'profitLifetime' => quantlab_period_return($series, 'all'),
        'profit7Days' => quantlab_period_return($series, 7),
        'profit30Days' => quantlab_period_return($series, 30),
        'profit90Days' => quantlab_period_return($series, 90),
        'unrealized' => quantlab_num($account['totalPerpUPL'] ?? 0),
        'realized' => quantlab_num($coin['cumRealisedPnl'] ?? $account['totalClosedPnl'] ?? 0),
        'available' => quantlab_num($account['totalAvailableBalance'] ?? $account['totalMarginBalance'] ?? 0),
        'position' => [
            'side' => $pos['side'] ?? 'None',
            'size' => quantlab_num($pos['size'] ?? 0),
            'avgPrice' => quantlab_num($pos['avgPrice'] ?? 0),
            'pnl' => quantlab_num($pos['unrealisedPnl'] ?? 0),
        ],
    ];
}

function quantlab_bybit_case(): array
{
    $wallet = quantlab_fetch_wallet();
    if (!$wallet) {
        throw new RuntimeException('Bybit wallet is empty');
    }
    $closed = quantlab_fetch_closed_pnl();
    $positions = quantlab_fetch_positions();
    $built = quantlab_build_equity($wallet['account'], $closed);
    $snapshots = quantlab_snapshot_daily($built['equity']);
    $series = quantlab_merge_snapshots($built['series'], $snapshots);
    $strategy = quantlab_summarize($wallet, $positions, $series);
    $strategy['profitLifetime'] = quantlab_period_return($series, 'all');
    $strategy['profit7Days'] = quantlab_period_return($series, 7);
    $strategy['profit30Days'] = quantlab_period_return($series, 30);
    $strategy['profit90Days'] = quantlab_period_return($series, 90);
    return [
        'strategy' => $strategy,
        'series' => $series,
        'source' => 'bybit-live',
    ];
}
