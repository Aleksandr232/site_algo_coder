<?php

function quantlab_data_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function quantlab_cache_read(string $file): ?string
{
    $path = quantlab_data_dir() . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        return null;
    }
    $text = file_get_contents($path);
    return $text === false ? null : $text;
}

function quantlab_cache_write(string $file, string $text): void
{
    file_put_contents(quantlab_data_dir() . DIRECTORY_SEPARATOR . $file, $text, LOCK_EX);
}

function quantlab_send_json(int $status, string $body, string $source, ?string $error = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Data-Source: ' . $source);
    if ($error) {
        header('X-Data-Error: ' . str_replace(["\r", "\n"], ' ', $error));
    }
    echo $body;
    exit;
}

function quantlab_send_error(int $status, string $error, string $detail = ''): void
{
    quantlab_send_json($status, json_encode([
        'error' => $error,
        'detail' => $detail,
    ], JSON_UNESCAPED_UNICODE), 'error');
}
