<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/bybit.php';

function quantlab_tinkoff_token(): string
{
    return quantlab_env('TINKOFF_TOKEN', quantlab_env('T_INVEST_TOKEN'));
}

function quantlab_tinkoff_account_id(): string
{
    return quantlab_env('TINKOFF_ACCOUNT_ID');
}

function quantlab_tinkoff_money($value): float
{
    if (!is_array($value)) {
        return quantlab_num($value);
    }
    return quantlab_num($value['units'] ?? 0) + quantlab_num($value['nano'] ?? 0) / 1e9;
}

function quantlab_tinkoff_post(string $serviceMethod, array $body = []): array
{
    $token = quantlab_tinkoff_token();
    if ($token === '') {
        throw new RuntimeException('Tinkoff token is missing');
    }
    $base = rtrim(quantlab_env('TINKOFF_API_BASE', 'https://invest-public-api.tinkoff.ru/rest'), '/');
    $url = $base . '/tinkoff.public.invest.api.contract.v1.' . $serviceMethod;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'x-app-name: amquantlab',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'Tinkoff request failed');
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Tinkoff bad JSON (' . $status . ')');
    }
    if ($status >= 400 || isset($json['code']) && $status >= 300) {
        $msg = (string) ($json['message'] ?? $json['description'] ?? 'Tinkoff error');
        throw new RuntimeException($msg . ' (' . $status . ')');
    }
    return $json;
}

function quantlab_tinkoff_portfolio(string $accountId): array
{
    try {
        return quantlab_tinkoff_post('OperationsService/GetPortfolio', [
            'accountId' => $accountId,
            'currency' => 'RUB',
        ]);
    } catch (Throwable $e) {
        return quantlab_tinkoff_post('OperationsService/GetPortfolio', [
            'accountId' => $accountId,
        ]);
    }
}

function quantlab_tinkoff_positions(string $accountId): array
{
    try {
        return quantlab_tinkoff_post('OperationsService/GetPositions', [
            'accountId' => $accountId,
        ]);
    } catch (Throwable $e) {
        return [];
    }
}

function quantlab_tinkoff_operations(string $accountId): array
{
    $rows = [];
    $cursor = '';
    $from = gmdate('Y-m-d\TH:i:s\Z', strtotime('-18 months'));
    $to = gmdate('Y-m-d\TH:i:s\Z');
    for ($i = 0; $i < 12; $i++) {
        $body = [
            'accountId' => $accountId,
            'from' => $from,
            'to' => $to,
            'limit' => 1000,
            'state' => 'OPERATION_STATE_EXECUTED',
            'withoutCommissions' => false,
            'withoutTrades' => false,
            'withoutOvernights' => false,
        ];
        if ($cursor !== '') {
            $body['cursor'] = $cursor;
        }
        try {
            $page = quantlab_tinkoff_post('OperationsService/GetOperationsByCursor', $body);
        } catch (Throwable $e) {
            if ($i === 0) {
                $legacy = quantlab_tinkoff_post('OperationsService/GetOperations', [
                    'accountId' => $accountId,
                    'from' => $from,
                    'to' => $to,
                    'state' => 'OPERATION_STATE_EXECUTED',
                ]);
                return $legacy['operations'] ?? [];
            }
            break;
        }
        $items = $page['items'] ?? $page['operations'] ?? [];
        $rows = array_merge($rows, $items);
        $next = (string) ($page['nextCursor'] ?? $page['next_cursor'] ?? '');
        $hasNext = !empty($page['hasNext']) || ($next !== '' && $next !== $cursor);
        if (!$hasNext || $next === '' || count($items) === 0) {
            break;
        }
        $cursor = $next;
    }
    return $rows;
}

function quantlab_tinkoff_op_skip(string $type): bool
{
    static $skip = [
        'OPERATION_TYPE_INPUT' => true,
        'OPERATION_TYPE_OUTPUT' => true,
        'OPERATION_TYPE_INPUT_SECURITIES' => true,
        'OPERATION_TYPE_OUTPUT_SECURITIES' => true,
        'OPERATION_TYPE_INP_MULTI' => true,
        'OPERATION_TYPE_OUT_MULTI' => true,
    ];
    return isset($skip[$type]);
}

function quantlab_tinkoff_daily_pnl(array $operations): array
{
    $days = [];
    foreach ($operations as $op) {
        $type = (string) ($op['type'] ?? $op['operationType'] ?? '');
        if (quantlab_tinkoff_op_skip($type)) {
            continue;
        }
        $date = (string) ($op['date'] ?? '');
        $day = substr($date, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            continue;
        }
        $days[$day] = ($days[$day] ?? 0) + quantlab_tinkoff_money($op['payment'] ?? []);
    }
    return $days;
}

function quantlab_tinkoff_build_equity(float $equity, array $operations): array
{
    $daily = quantlab_tinkoff_daily_pnl($operations);
    $days = array_keys($daily);
    sort($days);
    if (!$days) {
        $today = gmdate('Y-m-d');
        return [
            'series' => [['date' => $today, 'value' => 0.0, 'balance' => $equity]],
        ];
    }
    $cursor = $equity;
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
    return ['series' => $series];
}

function quantlab_tinkoff_snapshot_daily(float $equity): array
{
    $file = quantlab_data_dir() . DIRECTORY_SEPARATOR . 'tinkoff-daily.json';
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

function quantlab_tinkoff_is_cny(array $pos): bool
{
    $blob = strtoupper(
        ($pos['ticker'] ?? '') . ' ' .
        ($pos['figi'] ?? '') . ' ' .
        ($pos['name'] ?? '') . ' ' .
        ($pos['instrumentType'] ?? '')
    );
    if (str_contains($blob, 'CNY') || str_contains($blob, 'ЮАН')) {
        return true;
    }
    $ticker = strtoupper((string) ($pos['ticker'] ?? ''));
    return (bool) preg_match('/^CR[FGHJKMNQUVXZ]\d/i', $ticker);
}

function quantlab_tinkoff_qty(array $pos): float
{
    if (isset($pos['quantity'])) {
        return quantlab_tinkoff_money($pos['quantity']);
    }
    return quantlab_tinkoff_money($pos['balance'] ?? $pos['quantityLots'] ?? 0);
}

function quantlab_tinkoff_pick_position(array $portfolio, array $positionsPayload): array
{
    $candidates = $portfolio['positions'] ?? [];
    $futures = $positionsPayload['futures'] ?? [];
    foreach ($futures as $row) {
        $candidates[] = $row;
    }
    $best = null;
    $bestScore = -1.0;
    foreach ($candidates as $pos) {
        $qty = abs(quantlab_tinkoff_qty($pos));
        if ($qty < 1e-8) {
            continue;
        }
        $score = $qty;
        if (quantlab_tinkoff_is_cny($pos)) {
            $score += 1000;
        } elseif (strcasecmp((string) ($pos['instrumentType'] ?? ''), 'futures') === 0) {
            $score += 100;
        }
        if ($score > $bestScore) {
            $best = $pos;
            $bestScore = $score;
        }
    }
    return is_array($best) ? $best : [];
}

function quantlab_tinkoff_resolve_ticker(array $pos): string
{
    $ticker = trim((string) ($pos['ticker'] ?? ''));
    if ($ticker !== '') {
        return $ticker;
    }
    $figi = trim((string) ($pos['figi'] ?? ''));
    if ($figi === '') {
        return 'CNY';
    }
    try {
        $info = quantlab_tinkoff_post('InstrumentsService/GetInstrumentBy', [
            'idType' => 'INSTRUMENT_ID_TYPE_FIGI',
            'id' => $figi,
        ]);
        $inst = $info['instrument'] ?? $info;
        $name = trim((string) ($inst['ticker'] ?? $inst['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    } catch (Throwable $e) {
        // keep figi fallback
    }
    return $figi;
}

function quantlab_tinkoff_summarize(array $portfolio, array $pos, array $series, float $equity): array
{
    $qty = quantlab_tinkoff_qty($pos);
    $side = 'None';
    if ($qty > 1e-8) {
        $side = 'Buy';
    } elseif ($qty < -1e-8) {
        $side = 'Sell';
    }
    $ticker = $pos ? quantlab_tinkoff_resolve_ticker($pos) : 'CNY';
    $avg = quantlab_tinkoff_money($pos['averagePositionPrice'] ?? $pos['averagePositionPriceFifo'] ?? []);
    $upl = quantlab_tinkoff_money($pos['expectedYield'] ?? $portfolio['expectedYield'] ?? []);
    $cash = quantlab_tinkoff_money($portfolio['totalAmountCurrencies'] ?? []);
    $available = $cash > 0 ? $cash : $equity;
    return [
        'title' => 'Юань Тренд 2-5-15 · Тинькофф · тест',
        'venue' => 'Тинькофф Инвестиции',
        'instrument' => $ticker,
        'accountType' => 'T-Invest',
        'createdAt' => $series[0]['date'] ?? gmdate('Y-m-d'),
        'equity' => $equity,
        'currency' => 'RUB',
        'profitLifetime' => quantlab_period_return($series, 'all'),
        'profit7Days' => quantlab_period_return($series, 7),
        'profit30Days' => quantlab_period_return($series, 30),
        'profit90Days' => quantlab_period_return($series, 90),
        'unrealized' => $upl,
        'realized' => 0.0,
        'available' => $available,
        'position' => [
            'side' => $side,
            'size' => abs($qty),
            'avgPrice' => $avg,
            'pnl' => $upl,
            'ticker' => $ticker,
        ],
    ];
}

function quantlab_tinkoff_case(): array
{
    $accountId = quantlab_tinkoff_account_id();
    if ($accountId === '') {
        throw new RuntimeException('Tinkoff account id is missing');
    }
    $portfolio = quantlab_tinkoff_portfolio($accountId);
    $equity = quantlab_tinkoff_money($portfolio['totalAmountPortfolio'] ?? $portfolio['totalAmountCurrencies'] ?? []);
    if ($equity <= 0) {
        $equity = quantlab_tinkoff_money($portfolio['totalAmountFutures'] ?? [])
            + quantlab_tinkoff_money($portfolio['totalAmountCurrencies'] ?? []);
    }
    $positions = quantlab_tinkoff_positions($accountId);
    $operations = quantlab_tinkoff_operations($accountId);
    $built = quantlab_tinkoff_build_equity($equity, $operations);
    $snapshots = quantlab_tinkoff_snapshot_daily($equity);
    $series = quantlab_merge_snapshots($built['series'], $snapshots);
    $pos = quantlab_tinkoff_pick_position($portfolio, $positions);
    $strategy = quantlab_tinkoff_summarize($portfolio, $pos, $series, $equity);
    $strategy['profitLifetime'] = quantlab_period_return($series, 'all');
    $strategy['profit7Days'] = quantlab_period_return($series, 7);
    $strategy['profit30Days'] = quantlab_period_return($series, 30);
    $strategy['profit90Days'] = quantlab_period_return($series, 90);
    return [
        'strategy' => $strategy,
        'series' => $series,
        'source' => 'tinkoff-live',
    ];
}
