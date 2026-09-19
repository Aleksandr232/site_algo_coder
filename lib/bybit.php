<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/cache.php';

function quantlab_num($value): float
{
    $n = (float) $value;
    return is_finite($n) ? $n : 0.0;
}

function quantlab_bybit_tz(): DateTimeZone
{
    return new DateTimeZone('Asia/Shanghai');
}

function quantlab_bybit_today(): string
{
    return (new DateTimeImmutable('now', quantlab_bybit_tz()))->format('Y-m-d');
}

function quantlab_day_key($ms): string
{
    $ts = (int) floor(((float) $ms) / 1000);
    if ($ts <= 0) {
        $ts = time();
    }
    return (new DateTimeImmutable('@' . $ts))->setTimezone(quantlab_bybit_tz())->format('Y-m-d');
}

function quantlab_bybit_query(array $params): string
{
    ksort($params);
    $parts = [];
    foreach ($params as $name => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $parts[] = $name . '=' . $value;
    }
    return implode('&', $parts);
}

function quantlab_bybit_curl(string $url, array $headers, bool $verifySsl)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);
    $raw = curl_exec($ch);
    $err = $raw === false ? (curl_error($ch) ?: 'Bybit request failed') : '';
    curl_close($ch);
    return [$raw, $err];
}

function quantlab_bybit_get(string $path, array $params = []): array
{
    $key = quantlab_env('BYBIT_API_KEY');
    $secret = quantlab_env('BYBIT_API_SECRET');
    $base = rtrim(quantlab_env('BYBIT_BASE', 'https://api.bybit.com'), '/');
    if ($key === '' || $secret === '') {
        throw new RuntimeException('Bybit keys are missing');
    }

    $query = quantlab_bybit_query($params);
    $timestamp = (string) (int) floor(microtime(true) * 1000);
    $recv = '10000';
    $sign = hash_hmac('sha256', $timestamp . $key . $recv . $query, $secret);
    $url = $base . $path . ($query !== '' ? '?' . $query : '');
    $headers = [
        'X-BAPI-API-KEY: ' . $key,
        'X-BAPI-TIMESTAMP: ' . $timestamp,
        'X-BAPI-SIGN: ' . $sign,
        'X-BAPI-RECV-WINDOW: ' . $recv,
        'Accept: application/json',
    ];

    [$raw, $err] = quantlab_bybit_curl($url, $headers, true);
    if ($err !== '' && (stripos($err, 'ssl') !== false || stripos($err, 'certificate') !== false)) {
        [$raw, $err] = quantlab_bybit_curl($url, $headers, false);
    }
    if ($err !== '') {
        throw new RuntimeException($err);
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json) || (int) ($json['retCode'] ?? 1) !== 0) {
        $msg = is_array($json) ? ($json['retMsg'] ?? 'Bybit error') : 'Bybit error';
        $code = is_array($json) ? ($json['retCode'] ?? '?') : '?';
        throw new RuntimeException($msg . ' (' . $code . ')');
    }
    return $json['result'] ?? [];
}

function quantlab_bybit_windowed(string $path, array $base, int $lookbackDays = 180, int $limit = 100): array
{
    $rows = [];
    $end = time();
    $oldest = $end - $lookbackDays * 86400;
    while ($end > $oldest) {
        $start = max($oldest, $end - 7 * 86400 + 1);
        $cursor = '';
        for ($i = 0; $i < 8; $i++) {
            $params = $base;
            $params['startTime'] = (string) ($start * 1000);
            $params['endTime'] = (string) ($end * 1000);
            $params['limit'] = (string) $limit;
            if ($cursor !== '') {
                $params['cursor'] = $cursor;
            }
            $result = quantlab_bybit_get($path, $params);
            $list = $result['list'] ?? [];
            $rows = array_merge($rows, $list);
            $cursor = (string) ($result['nextPageCursor'] ?? '');
            if ($cursor === '' || count($list) < $limit) {
                break;
            }
        }
        $end = $start - 1;
    }
    return $rows;
}

function quantlab_bybit_unique(array $rows, array $keys): array
{
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = '';
        foreach ($keys as $key) {
            $id .= '|' . (string) ($row[$key] ?? '');
        }
        if ($id === '|' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = $row;
    }
    return $out;
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

function quantlab_bybit_usdt_coin(array $account): array
{
    foreach ($account['coin'] ?? [] as $item) {
        if (($item['coin'] ?? '') === 'USDT') {
            return $item;
        }
    }
    return [];
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

function quantlab_bybit_load_json(string $name): array
{
    $text = quantlab_cache_read($name);
    $data = $text !== null ? json_decode($text, true) : null;
    return is_array($data) ? $data : [];
}

function quantlab_bybit_file_key(string $prefix, string $category, string $symbol): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '', $category . '-' . $symbol) ?: 'BTCUSDT';
    return $prefix . '-' . $safe . '.json';
}

function quantlab_bybit_lookback_days(string $since): int
{
    $today = quantlab_bybit_today();
    $days = (int) round((strtotime($today . ' UTC') - strtotime($since . ' UTC')) / 86400) + 3;
    return max(14, min(180, $days));
}

function quantlab_bybit_base_coin(string $symbol): string
{
    $base = preg_replace('/(USDT|USDC|USD)$/', '', strtoupper($symbol));
    return $base !== '' ? $base : $symbol;
}

function quantlab_bybit_coin(array $account, string $coin): array
{
    foreach ($account['coin'] ?? [] as $item) {
        if (($item['coin'] ?? '') === $coin) {
            return $item;
        }
    }
    return [];
}

function quantlab_bybit_filter_since(array $rows, string $since, string $timeKey): array
{
    if ($since === '') {
        return $rows;
    }
    $out = [];
    foreach ($rows as $row) {
        if (quantlab_day_key($row[$timeKey] ?? 0) >= $since) {
            $out[] = $row;
        }
    }
    return $out;
}

function quantlab_fetch_closed_pnl(string $symbol = 'BTCUSDT', int $lookback = 180): array
{
    $file = quantlab_bybit_file_key('bybit-closed', 'linear', $symbol);
    $stored = quantlab_bybit_load_json($file);
    if (!$stored && $symbol === 'BTCUSDT') {
        $stored = quantlab_bybit_load_json('bybit-closed.json');
    }
    $need = count($stored) < 10 ? $lookback : 10;
    $fresh = quantlab_bybit_windowed('/v5/position/closed-pnl', [
        'category' => 'linear',
        'symbol' => $symbol,
    ], $need, 100);
    $rows = quantlab_bybit_unique(array_merge($stored, $fresh), ['orderId', 'updatedTime', 'closedPnl']);
    quantlab_cache_write($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $rows;
}

function quantlab_fetch_executions(string $category, string $symbol, int $lookback = 180): array
{
    $file = quantlab_bybit_file_key('bybit-exec', $category, $symbol);
    $stored = quantlab_bybit_load_json($file);
    $need = count($stored) < 10 ? $lookback : 10;
    $fresh = quantlab_bybit_windowed('/v5/execution/list', [
        'category' => $category,
        'symbol' => $symbol,
    ], $need, 100);
    $rows = quantlab_bybit_unique(array_merge($stored, $fresh), ['execId', 'orderId', 'execTime']);
    usort($rows, static function ($a, $b) {
        return ((int) ($a['execTime'] ?? 0)) <=> ((int) ($b['execTime'] ?? 0));
    });
    quantlab_cache_write($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $rows;
}

function quantlab_fetch_positions(string $symbol = 'BTCUSDT'): array
{
    try {
        $result = quantlab_bybit_get('/v5/position/list', [
            'category' => 'linear',
            'symbol' => $symbol,
        ]);
        return $result['list'] ?? [];
    } catch (Throwable $e) {
        return [];
    }
}

function quantlab_bybit_last_price(string $category, string $symbol): float
{
    try {
        $result = quantlab_bybit_get('/v5/market/tickers', [
            'category' => $category === 'spot' ? 'spot' : 'linear',
            'symbol' => $symbol,
        ]);
        $row = ($result['list'] ?? [])[0] ?? [];
        return quantlab_num($row['lastPrice'] ?? $row['markPrice'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function quantlab_spot_fifo(array $execs): array
{
    $lots = [];
    $daily = [];
    $realized = 0.0;
    foreach ($execs as $item) {
        $qty = quantlab_num($item['execQty'] ?? $item['orderQty'] ?? 0);
        $price = quantlab_num($item['execPrice'] ?? 0);
        $fee = quantlab_num($item['execFee'] ?? 0);
        $side = strtolower((string) ($item['side'] ?? ''));
        $day = quantlab_day_key($item['execTime'] ?? $item['updatedTime'] ?? 0);
        if ($qty <= 0 || $price <= 0) {
            continue;
        }
        $pnl = 0.0;
        if ($side === 'sell') {
            $left = $qty;
            while ($left > 1e-12 && $lots) {
                $take = min($left, $lots[0]['qty']);
                $pnl += ($price - $lots[0]['price']) * $take;
                $lots[0]['qty'] -= $take;
                $left -= $take;
                if ($lots[0]['qty'] <= 1e-12) {
                    array_shift($lots);
                }
            }
        } else {
            $lots[] = ['qty' => $qty, 'price' => $price];
        }
        $pnl -= $fee;
        $daily[$day] = ($daily[$day] ?? 0) + $pnl;
        $realized += $pnl;
    }
    $size = 0.0;
    $cost = 0.0;
    foreach ($lots as $lot) {
        $size += $lot['qty'];
        $cost += $lot['qty'] * $lot['price'];
    }
    return [
        'daily' => $daily,
        'realized' => $realized,
        'size' => $size,
        'avgPrice' => $size > 1e-12 ? $cost / $size : 0.0,
    ];
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

function quantlab_build_equity(array $wallet, array $closed, array $opts = []): array
{
    $usdt = quantlab_bybit_usdt_coin($wallet);
    $unrealized = quantlab_num($opts['unrealized'] ?? $wallet['totalPerpUPL'] ?? $usdt['unrealisedPnl'] ?? 0);
    $startBalance = quantlab_num($opts['start_balance'] ?? 0);
    $since = (string) ($opts['since'] ?? '');
    $today = quantlab_bybit_today();
    $daily = $opts['daily'] ?? quantlab_daily_pnl($closed);
    if ($since !== '') {
        foreach (array_keys($daily) as $day) {
            if ($day < $since) {
                unset($daily[$day]);
            }
        }
    }
    $realized = array_sum($daily);
    if ($startBalance > 1e-8) {
        $equity = $startBalance + $realized + $unrealized;
        $walletBal = $equity - $unrealized;
    } else {
        $equity = quantlab_num($opts['equity'] ?? $wallet['totalEquity'] ?? $usdt['equity'] ?? $wallet['totalWalletBalance'] ?? 0);
        $walletBal = quantlab_num($wallet['totalWalletBalance'] ?? $usdt['walletBalance'] ?? 0);
        if ($walletBal <= 0) {
            $walletBal = $equity - $unrealized;
        }
    }
    $from = $since !== '' && $since <= $today ? $since : ($daily ? min(array_keys($daily)) : $today);

    $cursor = $walletBal;
    $eod = [];
    $dt = new DateTimeImmutable($today, new DateTimeZone('UTC'));
    $end = new DateTimeImmutable($from, new DateTimeZone('UTC'));
    while ($dt >= $end) {
        $day = $dt->format('Y-m-d');
        $eod[$day] = $cursor;
        $cursor -= $daily[$day] ?? 0;
        $dt = $dt->modify('-1 day');
    }
    ksort($eod);

    $start = $startBalance > 1e-8 ? $startBalance : $cursor;
    $base = abs($start) > 1e-8 ? $start : ($equity ?: 1);
    $series = [];
    foreach ($eod as $day => $balance) {
        $bal = $day === $today ? $equity : $balance;
        $series[] = [
            'date' => $day,
            'value' => (($bal - $base) / $base) * 100,
            'balance' => $bal,
        ];
    }
    return [
        'equity' => $equity,
        'series' => $series,
        'start' => $base,
        'realized' => $realized,
    ];
}

function quantlab_implied_start(array $series): float
{
    foreach ($series as $row) {
        $bal = quantlab_num($row['balance'] ?? 0);
        $val = quantlab_num($row['value'] ?? 0);
        $denom = 1 + $val / 100;
        if (abs($bal) > 1e-8 && abs($denom) > 1e-8) {
            return $bal / $denom;
        }
    }
    $first = quantlab_num($series[0]['balance'] ?? 0);
    return abs($first) > 1e-8 ? $first : 1.0;
}

function quantlab_rebase_series(array $series, ?float $start = null): array
{
    if (!$series) {
        return [];
    }
    usort($series, static fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    $base = $start;
    if ($base === null || abs($base) < 1e-8) {
        $base = quantlab_implied_start($series);
    }
    if (abs($base) < 1e-8) {
        $base = 1.0;
    }
    foreach ($series as &$row) {
        $bal = quantlab_num($row['balance'] ?? 0);
        $row['value'] = (($bal - $base) / $base) * 100;
    }
    unset($row);
    return array_values($series);
}

function quantlab_period_return(array $series, $days): float
{
    if (!$series) {
        return 0.0;
    }
    $last = $series[count($series) - 1];
    $lastBal = quantlab_num($last['balance'] ?? 0);
    if ($days === 'all') {
        return quantlab_num($last['value'] ?? 0);
    }
    $anchor = (string) ($last['date'] ?? quantlab_bybit_today());
    $target = date('Y-m-d', strtotime($anchor . ' -' . (int) $days . ' days'));
    $then = null;
    foreach ($series as $row) {
        if (($row['date'] ?? '') <= $target) {
            $then = $row;
        }
    }
    if ($then === null) {
        return quantlab_num($last['value'] ?? 0);
    }
    $thenBal = quantlab_num($then['balance'] ?? 0);
    if (abs($thenBal) < 1e-8) {
        return 0.0;
    }
    return (($lastBal - $thenBal) / $thenBal) * 100;
}

function quantlab_snapshot_daily(float $equity, string $slug = ''): array
{
    $name = $slug !== '' ? ('bybit-daily-' . preg_replace('/[^A-Za-z0-9_-]+/', '', $slug) . '.json') : 'bybit-daily.json';
    $file = quantlab_data_dir() . DIRECTORY_SEPARATOR . $name;
    $rows = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }
    $today = quantlab_bybit_today();
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
    $byDate = [];
    foreach ($series as $row) {
        if (!empty($row['date'])) {
            $byDate[$row['date']] = $row;
        }
    }
    foreach ($snapshots as $row) {
        $date = (string) ($row['date'] ?? '');
        if ($date === '') {
            continue;
        }
        $byDate[$date] = [
            'date' => $date,
            'balance' => quantlab_num($row['balance'] ?? 0),
            'value' => $byDate[$date]['value'] ?? 0,
        ];
    }
    ksort($byDate);
    return quantlab_rebase_series(array_values($byDate), $series ? quantlab_implied_start($series) : null);
}

function quantlab_summarize(array $wallet, array $positions, array $series, float $realized = 0.0, array $cfg = []): array
{
    $account = $wallet['account'] ?? [];
    $usdt = quantlab_bybit_usdt_coin($account);
    $equity = quantlab_num($cfg['equity'] ?? $account['totalEquity'] ?? $usdt['equity'] ?? $account['totalWalletBalance'] ?? 0);
    $pos = ['side' => 'None', 'size' => 0, 'avgPrice' => 0, 'unrealisedPnl' => 0];
    foreach ($positions as $item) {
        if (quantlab_num($item['size'] ?? 0) > 0) {
            $pos = $item;
            break;
        }
    }
    if ($positions && quantlab_num($pos['size'] ?? 0) === 0) {
        $pos = $positions[0];
    }
    $unrealized = quantlab_num($pos['unrealisedPnl'] ?? $cfg['unrealized'] ?? 0);
    $available = quantlab_num($account['totalAvailableBalance'] ?? $account['totalMarginBalance'] ?? 0);
    if ($available <= 1e-8) {
        $available = quantlab_num($usdt['availableToWithdraw'] ?? $usdt['walletBalance'] ?? 0);
    }
    $symbol = (string) ($cfg['symbol'] ?? 'BTCUSDT');
    $market = (($cfg['category'] ?? 'linear') === 'spot') ? 'spot' : 'linear';
    $marketLabel = $market === 'spot' ? 'Spot' : 'Perp';
    return [
        'title' => (string) ($cfg['title'] ?? 'BTC Trend · Bybit · тест'),
        'venue' => 'Bybit',
        'instrument' => $symbol . ' ' . $marketLabel,
        'market' => $market,
        'accountType' => $wallet['accountType'] ?? 'UNIFIED',
        'createdAt' => $cfg['since'] ?? ($series[0]['date'] ?? quantlab_bybit_today()),
        'equity' => $equity,
        'currency' => 'USDT',
        'profitLifetime' => quantlab_period_return($series, 'all'),
        'profit7Days' => quantlab_period_return($series, 7),
        'profit30Days' => quantlab_period_return($series, 30),
        'profit90Days' => quantlab_period_return($series, 90),
        'unrealized' => $unrealized,
        'realized' => $realized,
        'available' => $available,
        'position' => [
            'side' => $pos['side'] ?? 'None',
            'size' => quantlab_num($pos['size'] ?? 0),
            'avgPrice' => quantlab_num($pos['avgPrice'] ?? 0),
            'pnl' => quantlab_num($pos['unrealisedPnl'] ?? $unrealized),
        ],
    ];
}

function quantlab_bybit_config(?array $cfg = null): array
{
    $defaults = [
        'slug' => 'bybit-btc',
        'title' => 'BTC Trend · Bybit · тест',
        'symbol' => 'BTCUSDT',
        'category' => 'linear',
        'since' => '2026-08-31',
        'start_balance' => 0.0,
        'is_test' => 1,
    ];
    if (!$cfg) {
        return $defaults;
    }
    $symbol = strtoupper(preg_replace('/\s+/', '', (string) ($cfg['symbol'] ?? 'BTCUSDT')) ?: 'BTCUSDT');
    $category = (($cfg['category'] ?? 'linear') === 'spot') ? 'spot' : 'linear';
    $since = (string) ($cfg['since'] ?? $defaults['since']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
        $since = $defaults['since'];
    }
    return [
        'slug' => (string) ($cfg['slug'] ?? $defaults['slug']),
        'title' => (string) ($cfg['title'] ?? $defaults['title']),
        'symbol' => $symbol,
        'category' => $category,
        'since' => $since,
        'start_balance' => quantlab_num($cfg['start_balance'] ?? 0),
        'is_test' => !empty($cfg['is_test']) ? 1 : 0,
    ];
}

function quantlab_bybit_case(?array $cfg = null): array
{
    $cfg = quantlab_bybit_config($cfg);
    $wallet = quantlab_fetch_wallet();
    if (!$wallet) {
        throw new RuntimeException('Bybit wallet is empty');
    }
    $lookback = quantlab_bybit_lookback_days($cfg['since']);
    $account = $wallet['account'] ?? [];
    $positions = [];
    $daily = [];
    $unrealized = 0.0;
    $equityOverride = 0.0;
    $closed = [];

    if ($cfg['category'] === 'spot') {
        $execs = quantlab_fetch_executions('spot', $cfg['symbol'], $lookback);
        $fifo = quantlab_spot_fifo($execs);
        $daily = $fifo['daily'];
        $last = quantlab_bybit_last_price('spot', $cfg['symbol']);
        $base = quantlab_bybit_base_coin($cfg['symbol']);
        $coin = quantlab_bybit_coin($account, $base);
        $size = quantlab_num($coin['walletBalance'] ?? $coin['equity'] ?? $fifo['size']);
        $avg = $fifo['avgPrice'];
        $unrealized = $size > 0 && $last > 0 ? ($last - $avg) * $size : 0.0;
        $equityOverride = $size * ($last ?: $avg);
        $positions = [[
            'side' => $size > 1e-12 ? 'Buy' : 'None',
            'size' => $size,
            'avgPrice' => $avg,
            'unrealisedPnl' => $unrealized,
        ]];
        $closed = [];
    } else {
        $closed = quantlab_bybit_filter_since(
            quantlab_fetch_closed_pnl($cfg['symbol'], $lookback),
            $cfg['since'],
            'updatedTime'
        );
        $positions = quantlab_fetch_positions($cfg['symbol']);
        foreach ($positions as $item) {
            if (quantlab_num($item['size'] ?? 0) > 0) {
                $unrealized = quantlab_num($item['unrealisedPnl'] ?? 0);
                break;
            }
        }
    }

    $buildOpts = [
        'since' => $cfg['since'],
        'start_balance' => $cfg['start_balance'],
        'unrealized' => $unrealized,
    ];
    if ($daily) {
        $buildOpts['daily'] = $daily;
    }
    if ($equityOverride > 0) {
        $buildOpts['equity'] = $equityOverride;
    }
    $built = quantlab_build_equity($account, $closed, $buildOpts);
    quantlab_snapshot_daily($built['equity'], (string) $cfg['slug']);
    $series = quantlab_rebase_series($built['series'], quantlab_num($built['start'] ?? 0));
    $cfg['equity'] = $built['equity'];
    $cfg['unrealized'] = $unrealized;
    $strategy = quantlab_summarize($wallet, $positions, $series, quantlab_num($built['realized'] ?? 0), $cfg);
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
