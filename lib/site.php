<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quantlab_venue_logo(string $id): string
{
    static $marks = [
        'finam' => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="32" height="32" rx="8" fill="#1657C2"/><path fill="#fff" d="M14.15 6.6h3.7v4.1c2.9.3 5 2.35 5 5.3s-2.1 5-5 5.3v4.1h-3.7v-4.1c-2.9-.3-5-2.35-5-5.3s2.1-5 5-5.3V6.6zm1.85 6.55c-1.6 0-2.8.95-2.8 2.25s1.2 2.25 2.8 2.25 2.8-.95 2.8-2.25-1.2-2.25-2.8-2.25z"/></svg>',
        'tinkoff' => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="32" height="32" rx="8" fill="#FFDD2D"/><path fill="#111" d="M7 8.1h18v4.5h-6.65V24h-4.7V12.6H7z"/></svg>',
        'bybit' => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="32" height="32" rx="8" fill="#F7A600"/><path fill="#141414" d="M10.2 7.4h7.1c3.15 0 5.5 2.05 5.5 4.85 0 1.85-1.05 3.3-2.7 4.05 2 .7 3.25 2.35 3.25 4.45 0 3.1-2.5 5.15-6 5.15H10.2V7.4zm4.35 3.65v4.35h2.7c1.55 0 2.5-.85 2.5-2.2s-.95-2.15-2.5-2.15h-2.7zm0 7.35v4.7h3.2c1.7 0 2.75-.9 2.75-2.35s-1.05-2.35-2.75-2.35h-3.2z"/></svg>',
        'okx' => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="32" height="32" rx="8" fill="#111"/><rect x="6" y="6" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="13.1" y="6" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="20.2" y="6" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="6" y="13.1" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="20.2" y="13.1" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="6" y="20.2" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="13.1" y="20.2" width="5.8" height="5.8" rx="1.1" fill="#fff"/><rect x="20.2" y="20.2" width="5.8" height="5.8" rx="1.1" fill="#fff"/></svg>',
        'binance' => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="32" height="32" rx="8" fill="#1A1B1F"/><g fill="#F0B90B" transform="translate(16 16)"><path d="M0-4.2 4.2 0 0 4.2-4.2 0Z"/><path d="M0-13.2 3.3-9.9 0-6.6-3.3-9.9Z"/><path d="M0 6.6 3.3 9.9 0 13.2-3.3 9.9Z"/><path d="M-13.2 0-9.9-3.3-6.6 0-9.9 3.3Z"/><path d="M6.6 0 9.9-3.3 13.2 0 9.9 3.3Z"/></g></svg>',
    ];
    if (!isset($marks[$id])) {
        return '';
    }
    return '<span class="venue-logo venue-logo-' . $id . '" aria-hidden="true">' . $marks[$id] . '</span>';
}

function quantlab_venue_chip(string $id, string $label): string
{
    return '<span class="venue-chip">' . quantlab_venue_logo($id) . '<span>' . quantlab_h($label) . '</span></span>';
}

function quantlab_ticker_group_html(): string
{
    $items = [
        ['finam', 'FINAM'],
        ['tinkoff', 'TINKOFF'],
        ['bybit', 'BYBIT'],
        ['okx', 'OKX'],
        ['binance', 'BINANCE'],
    ];
    $html = '';
    foreach ($items as [$id, $label]) {
        $html .= '<span class="ticker-item">' . quantlab_venue_logo($id) . $label . '</span>';
    }
    $html .= '<span>MOEX</span><span>BTC</span><span>ETH</span><span>CNYRUB</span>';
    return $html;
}

function quantlab_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host;
}

function quantlab_site_url(): string
{
    $configured = rtrim(quantlab_env('SITE_URL'), '/');
    if ($configured !== '') {
        return $configured;
    }
    return rtrim(quantlab_request_origin(), '/');
}

function quantlab_site_email(): string
{
    $email = quantlab_env('SITE_EMAIL', quantlab_env('SMTP_FROM', 'info@amquantlab.ru'));
    return $email !== '' ? $email : 'info@amquantlab.ru';
}

function quantlab_public_path(string $path): string
{
    $path = '/' . trim($path, '/');
    if ($path === '/') {
        return '/';
    }
    return $path . '/';
}

function quantlab_abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path === '' || $path === '/') {
        return quantlab_site_url() . '/';
    }
    if (preg_match('/\.[a-z0-9]+$/i', $path)) {
        return quantlab_site_url() . '/' . ltrim($path, '/');
    }
    return quantlab_site_url() . quantlab_public_path($path);
}

function quantlab_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return $path ? $path : '/';
}

function quantlab_enforce_canonical(string $path): string
{
    $canonicalPath = quantlab_public_path($path);
    $canonical = quantlab_abs_url($canonicalPath);
    $requestPath = quantlab_request_path();
    $normalizedRequest = $requestPath === '/' ? '/' : rtrim($requestPath, '/') . '/';

    $redirect = $normalizedRequest !== $canonicalPath;

    $site = quantlab_site_url();
    $origin = rtrim(quantlab_request_origin(), '/');
    if (quantlab_env('SITE_URL') !== '' && strcasecmp($origin, $site) !== 0) {
        $redirect = true;
    }

    if ($redirect) {
        header('Location: ' . $canonical, true, 301);
        exit;
    }

    return $canonical;
}

function quantlab_json_ld(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script type="application/ld+json">' . $json . '</script>';
}

function quantlab_render_crumbs(array $items): string
{
    $html = '<nav class="crumbs" aria-label="Хлебные крошки"><ol>';
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [],
    ];
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        $url = quantlab_abs_url($item['path']);
        $name = (string) $item['name'];
        $html .= '<li>';
        if ($i < $last) {
            $html .= '<a href="' . quantlab_h($item['path'] === '/' ? '/' : quantlab_public_path($item['path'])) . '">' . quantlab_h($name) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . quantlab_h($name) . '</span>';
        }
        $html .= '</li>';
        $ld['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $name,
            'item' => $url,
        ];
    }
    $html .= '</ol></nav>';
    return $html . quantlab_json_ld($ld);
}

function quantlab_icon_href(): string
{
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%2306080d'/%3E%3Cpath d='M7 22 L13 10 L19 18 L25 8' fill='none' stroke='%233dffa4' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E";
}

function quantlab_font_href(): string
{
    return 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap';
}

function quantlab_font_links(): void
{
    $href = quantlab_h(quantlab_font_href());
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="<?= $href ?>" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="<?= $href ?>" /></noscript>
    <?php
}

function quantlab_head_verification(): void
{
    $yandex = quantlab_env('YANDEX_VERIFICATION', 'd94405cb4c18d9e3');
    $google = quantlab_env('GOOGLE_SITE_VERIFICATION', 'Z2TzFu1RkbL0doij_GukqPyVW3me4BjC7EH-Lw6bsDo');
    if ($yandex !== '') {
        echo '    <meta name="yandex-verification" content="' . quantlab_h($yandex) . '" />' . "\n";
    }
    if ($google !== '') {
        echo '    <meta name="google-site-verification" content="' . quantlab_h($google) . '" />' . "\n";
    }
}

function quantlab_render_start(array $meta): void
{
    $canonical = (string) $meta['canonical'];
    $title = (string) $meta['title'];
    $description = (string) ($meta['description'] ?? 'AM QuantLab — торговые роботы и финтех-сервисы.');
    $robots = (string) ($meta['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    if ($robots === 'index,follow') {
        $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }
    $type = (string) ($meta['og_type'] ?? 'website');
    $keywords = trim((string) ($meta['keywords'] ?? ''));
    $image = trim((string) ($meta['image'] ?? ''));
    $imageAbs = $image !== '' ? quantlab_abs_url($image) : '';
    $published = trim((string) ($meta['published_at'] ?? ''));
    $modified = trim((string) ($meta['modified_at'] ?? ''));
    $active = (string) ($meta['active'] ?? '');
    $extraHead = (string) ($meta['extra_head'] ?? '');
    $yandex = quantlab_env('YANDEX_VERIFICATION', 'd94405cb4c18d9e3');
    $google = quantlab_env('GOOGLE_SITE_VERIFICATION', 'Z2TzFu1RkbL0doij_GukqPyVW3me4BjC7EH-Lw6bsDo');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= quantlab_h($title) ?></title>
    <meta name="description" content="<?= quantlab_h($description) ?>" />
    <?php if ($keywords !== ''): ?>
    <meta name="keywords" content="<?= quantlab_h($keywords) ?>" />
    <?php endif; ?>
    <meta name="robots" content="<?= quantlab_h($robots) ?>" />
    <meta name="googlebot" content="<?= quantlab_h($robots) ?>" />
    <meta name="yandex" content="<?= quantlab_h($robots) ?>" />
    <?php if ($yandex !== ''): ?>
    <meta name="yandex-verification" content="<?= quantlab_h($yandex) ?>" />
    <?php endif; ?>
    <?php if ($google !== ''): ?>
    <meta name="google-site-verification" content="<?= quantlab_h($google) ?>" />
    <?php endif; ?>
    <link rel="canonical" href="<?= quantlab_h($canonical) ?>" />
    <link rel="alternate" type="application/rss+xml" title="Блог AM QuantLab" href="<?= quantlab_h(quantlab_abs_url('rss.xml')) ?>" />
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= quantlab_h(quantlab_abs_url('sitemap.xml')) ?>" />
    <meta property="og:type" content="<?= quantlab_h($type) ?>" />
    <meta property="og:locale" content="ru_RU" />
    <meta property="og:site_name" content="AM QuantLab" />
    <meta property="og:title" content="<?= quantlab_h($title) ?>" />
    <meta property="og:description" content="<?= quantlab_h($description) ?>" />
    <meta property="og:url" content="<?= quantlab_h($canonical) ?>" />
    <?php if ($imageAbs !== ''): ?>
    <meta property="og:image" content="<?= quantlab_h($imageAbs) ?>" />
    <meta property="og:image:alt" content="<?= quantlab_h($title) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= quantlab_h($title) ?>" />
    <meta name="twitter:description" content="<?= quantlab_h($description) ?>" />
    <meta name="twitter:image" content="<?= quantlab_h($imageAbs) ?>" />
    <?php else: ?>
    <meta name="twitter:card" content="summary" />
    <?php endif; ?>
    <?php if ($published !== ''): ?>
    <meta property="article:published_time" content="<?= quantlab_h($published) ?>" />
    <?php endif; ?>
    <?php if ($modified !== ''): ?>
    <meta property="article:modified_time" content="<?= quantlab_h($modified) ?>" />
    <?php endif; ?>
    <link rel="icon" href="<?= quantlab_icon_href() ?>" />
    <?php quantlab_font_links(); ?>
    <link rel="stylesheet" href="/css/styles.css" />
    <?= $extraHead ?>
  </head>
  <body class="<?= quantlab_h((string) ($meta['body_class'] ?? 'page-inner')) ?>">
    <div class="noise" aria-hidden="true"></div>
    <div class="grid-bg" aria-hidden="true"></div>
    <div class="vignette" aria-hidden="true"></div>
    <div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>
    <div class="orb orb-a" aria-hidden="true"></div>
    <div class="orb orb-b" aria-hidden="true"></div>
    <div class="orb orb-c" aria-hidden="true"></div>
    <canvas id="fx-layer" class="fx-layer" aria-hidden="true"></canvas>
    <canvas id="candle-bg" class="candle-bg page-candle-bg" aria-hidden="true"></canvas>
    <header class="header" id="top">
      <div class="container header-inner">
        <div class="logo-block">
          <a class="logo" href="/">
            <span class="logo-mark" aria-hidden="true"></span>
            AM Quant<span>Lab</span>
          </a>
          <span class="sys-status" aria-hidden="true"><span class="pulse"></span> live</span>
        </div>
        <nav class="nav" id="nav">
          <a href="/#markets">Рынки</a>
          <a href="/#venues">Площадки</a>
          <a href="/#stack">Стек</a>
          <a href="/#algos">Продукты</a>
          <a href="/#dashboards">Дашборды</a>
          <a href="/#case">Кейсы</a>
          <a href="/blog/"<?= $active === 'blog' ? ' aria-current="page"' : '' ?>>Блог</a>
          <a href="/#contact">Контакт</a>
        </nav>
        <a class="btn btn-sm" href="/#contact">Заказать робота</a>
        <button class="burger" id="burger" type="button" aria-label="Открыть меню">
          <span></span><span></span>
        </button>
      </div>
    </header>
    <div class="ticker ticker-inner" aria-hidden="true">
      <div class="ticker-track">
        <div class="ticker-group">
          <?= quantlab_ticker_group_html() ?>
        </div>
        <div class="ticker-group">
          <?= quantlab_ticker_group_html() ?>
        </div>
      </div>
    </div>
    <main class="page-main">
    <?php
}

function quantlab_render_end(): void
{
    ?>
    </main>
    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-top">
          <div>
            <a class="logo" href="/">AM Quant<span>Lab</span></a>
            <p>Роботы на Node.js и Go. API Финам, Тинькофф Инвестиции, Bybit, OKX, Binance.</p>
          </div>
          <span class="sys-status"><span class="pulse"></span> systems online</span>
        </div>
        <p class="footer-links">
          <a href="/">Главная</a>
          <a href="/blog/">Блог</a>
          <a href="/#case">Кейсы</a>
          <a href="/#contact">Контакт</a>
          <a href="mailto:<?= quantlab_h(quantlab_site_email()) ?>"><?= quantlab_h(quantlab_site_email()) ?></a>
          <a href="#privacy" data-privacy>Политика конфиденциальности</a>
        </p>
        <p class="disclaimer">
          Материал не является индивидуальной инвестиционной рекомендацией. Доходность в прошлом
          не гарантирует результат в будущем.
        </p>
      </div>
    </footer>
    <script src="/js/motion.js"></script>
    <script src="/js/privacy.js"></script>
    <script>
      (function () {
        var burger = document.getElementById("burger");
        var nav = document.getElementById("nav");
        if (!burger || !nav) return;
        burger.addEventListener("click", function () { nav.classList.toggle("is-open"); });
        nav.querySelectorAll("a").forEach(function (link) {
          link.addEventListener("click", function () { nav.classList.remove("is-open"); });
        });
      })();
    </script>
  </body>
</html>
    <?php
}
