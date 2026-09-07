<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) && $uri !== '' ? $uri : '/';
$root = __DIR__;

if (preg_match('#^/(data|lib|install\.sql)(/|$)#', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

if ($uri === '/sitemap.xml') {
    require $root . DIRECTORY_SEPARATOR . 'sitemap.php';
    return true;
}
if ($uri === '/robots.txt') {
    require $root . DIRECTORY_SEPARATOR . 'robots.php';
    return true;
}
if ($uri === '/rss.xml') {
    require $root . DIRECTORY_SEPARATOR . 'rss.php';
    return true;
}

$file = $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri !== '/' && substr($uri, -1) !== '/' && pathinfo($uri, PATHINFO_EXTENSION) === '') {
    header('Location: ' . $uri . '/', true, 301);
    exit;
}

$dir = $root . str_replace('/', DIRECTORY_SEPARATOR, rtrim($uri, '/'));
if ($uri === '/') {
    require $root . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}
if (is_dir($dir)) {
    if (is_file($dir . DIRECTORY_SEPARATOR . 'index.php')) {
        require $dir . DIRECTORY_SEPARATOR . 'index.php';
        return true;
    }
    if (is_file($dir . DIRECTORY_SEPARATOR . 'index.html')) {
        return false;
    }
}

http_response_code(404);
require $root . DIRECTORY_SEPARATOR . '404.php';
return true;
