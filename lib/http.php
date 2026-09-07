<?php

function quantlab_http_get(string $url, array $headers = [], bool $follow = true): array
{
    $cookie = '';
    $current = $url;
    for ($i = 0; $i < 6; $i++) {
        $reqHeaders = $headers;
        if ($cookie !== '') {
            $reqHeaders[] = 'Cookie: ' . $cookie;
        }
        $res = quantlab_http_exec($current, $reqHeaders, false, true);
        if (!$res['ok'] && (stripos($res['error'], 'ssl') !== false || stripos($res['error'], 'certificate') !== false)) {
            $res = quantlab_http_exec($current, $reqHeaders, false, false);
        }
        if (!$res['ok']) {
            throw new RuntimeException($res['error']);
        }
        if (!empty($res['cookie'])) {
            $cookie = $cookie === '' ? $res['cookie'] : $cookie . '; ' . $res['cookie'];
        }
        $res['cookie'] = $cookie;
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return $res;
        }
        if ($follow && $res['status'] >= 300 && $res['status'] < 400) {
            $next = $res['location'] !== '' ? $res['location'] : $current;
            if ($next !== '' && $next[0] === '/') {
                $parts = parse_url($current);
                $next = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $next;
            }
            $current = $next;
            continue;
        }
        return $res;
    }
    throw new RuntimeException('Too many redirects');
}

function quantlab_http_exec(string $url, array $headers, bool $follow, bool $verifySsl): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => $err ?: 'curl failed'];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $headerText = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $cookies = [];
    $location = '';
    foreach (explode("\n", $headerText) as $line) {
        $line = trim($line);
        if (stripos($line, 'Set-Cookie:') === 0) {
            $cookie = trim(substr($line, 11));
            $cookies[] = strtok($cookie, ';');
        }
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }
    return [
        'ok' => true,
        'status' => $status,
        'body' => $body,
        'cookie' => implode('; ', array_filter($cookies)),
        'location' => $location,
        'url' => $finalUrl,
    ];
}
