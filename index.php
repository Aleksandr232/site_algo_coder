<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$title = 'AM QuantLab — торговые алгоритмы и финтех-сервисы';
$description = 'AM QuantLab пишет торговых роботов для Финам, Тинькофф Инвестиции, Bybit, OKX и Binance. Node.js, Go, API, сервисы для финтех-продуктов.';
$canonical = quantlab_abs_url('/');
$posts = array_slice(quantlab_blog_published(), 0, 3);
$formSent = (string) ($_GET['sent'] ?? '') === '1';
$yandex = quantlab_env('YANDEX_VERIFICATION', 'd94405cb4c18d9e3');
$google = quantlab_env('GOOGLE_SITE_VERIFICATION', 'Z2TzFu1RkbL0doij_GukqPyVW3me4BjC7EH-Lw6bsDo');
$year = date('Y');

$blogList = [];
foreach ($posts as $i => $item) {
    $blogList[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'url' => quantlab_abs_url('blog/' . $item['slug']),
        'name' => $item['title'],
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= quantlab_h($title) ?></title>
    <meta name="description" content="<?= quantlab_h($description) ?>" />
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
    <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
    <meta name="yandex" content="index, follow" />
    <?php if ($yandex !== ''): ?>
    <meta name="yandex-verification" content="<?= quantlab_h($yandex) ?>" />
    <?php endif; ?>
    <?php if ($google !== ''): ?>
    <meta name="google-site-verification" content="<?= quantlab_h($google) ?>" />
    <?php endif; ?>
    <link rel="canonical" href="<?= quantlab_h($canonical) ?>" />
    <link rel="sitemap" type="application/xml" href="<?= quantlab_h(quantlab_abs_url('sitemap.xml')) ?>" />
    <link rel="alternate" type="application/rss+xml" title="Блог AM QuantLab" href="<?= quantlab_h(quantlab_abs_url('rss.xml')) ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="ru_RU" />
    <meta property="og:site_name" content="AM QuantLab" />
    <meta property="og:title" content="<?= quantlab_h($title) ?>" />
    <meta property="og:description" content="<?= quantlab_h($description) ?>" />
    <meta property="og:url" content="<?= quantlab_h($canonical) ?>" />
    <meta name="twitter:card" content="summary" />
    <?= quantlab_json_ld([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'AM QuantLab',
        'url' => $canonical,
        'inLanguage' => 'ru-RU',
        'potentialAction' => ['@type' => 'ReadAction', 'target' => quantlab_abs_url('blog')],
    ]) ?>
    <?= quantlab_json_ld([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'AM QuantLab',
        'url' => $canonical,
        'email' => quantlab_site_email(),
        'description' => 'Разработка торговых алгоритмов, роботов и финтех-сервисов под Финам, Тинькофф Инвестиции, Bybit, OKX и Binance.',
    ]) ?>
    <?php if ($blogList): ?>
    <?= quantlab_json_ld([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Свежие статьи AM QuantLab',
        'itemListElement' => $blogList,
    ]) ?>
    <?php endif; ?>
    <link rel="icon" href="<?= quantlab_icon_href() ?>" />
    <?php quantlab_font_links(); ?>
    <link rel="stylesheet" href="/css/styles.css" />
  </head>
  <body>
    <div class="noise" aria-hidden="true"></div>
    <div class="grid-bg" aria-hidden="true"></div>
    <div class="vignette" aria-hidden="true"></div>
    <div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>
    <div class="orb orb-a" aria-hidden="true"></div>
    <div class="orb orb-b" aria-hidden="true"></div>
    <div class="orb orb-c" aria-hidden="true"></div>
    <canvas id="fx-layer" class="fx-layer" aria-hidden="true"></canvas>

    <header class="header" id="top">
      <div class="container header-inner">
        <div class="logo-block">
          <a class="logo" href="#top">
            <span class="logo-mark" aria-hidden="true"></span>
            AM Quant<span>Lab</span>
          </a>
          <span class="sys-status" aria-hidden="true"><span class="pulse"></span> live</span>
        </div>
        <nav class="nav" id="nav">
          <a href="#markets">Рынки</a>
          <a href="#venues">Площадки</a>
          <a href="#stack">Стек</a>
          <a href="#algos">Продукты</a>
          <a href="#dashboards">Дашборды</a>
          <a href="#case">Кейсы</a>
          <a href="<?= $posts ? '#blog' : '/blog/' ?>">Блог</a>
          <a href="#process">Процесс</a>
          <a href="#contact">Контакт</a>
        </nav>
        <a class="btn btn-sm" href="#contact">Заказать робота</a>
        <button class="burger" id="burger" type="button" aria-label="Открыть меню">
          <span></span><span></span>
        </button>
      </div>
    </header>

    <main>
      <section class="hero">
        <canvas id="candle-bg" class="candle-bg" aria-hidden="true"></canvas>
        <div class="trade-legend" aria-hidden="true">
          <span class="leg-buy">Покупка</span>
          <span class="leg-sell">Продажа</span>
          <span class="leg-tp">Профит</span>
        </div>
        <div class="container hero-grid">
          <div class="hero-copy">
            <p class="eyebrow">
              <span class="pulse"></span>
              AM QuantLab · Node.js · Go · API
            </p>
            <h1>Пишем торговых роботов <em>и сервисы для финтех-продуктов</em></h1>
            <p class="lead">
              Пишем торговых роботов под Финам, Тинькофф Инвестиции, Bybit, OKX и Binance.
              Исполнение через официальные API. Стек — Node.js и Go.
            </p>
            <div class="hero-actions">
              <a class="btn" href="#venues">Площадки</a>
              <a class="btn btn-ghost" href="#dashboards">Дашборды</a>
            </div>
            <div class="venue-chips" aria-label="Площадки">
              <span>Финам</span>
              <span>Тинькофф</span>
              <span>Bybit</span>
              <span>OKX</span>
              <span>Binance</span>
            </div>
            <dl class="hero-stats">
              <div>
                <dt>Доходность кейса</dt>
                <dd id="hero-pnl">+31.8%</dd>
              </div>
              <div>
                <dt>Правило риска</dt>
                <dd>2 / −5 / +15</dd>
              </div>
              <div>
                <dt>Площадки</dt>
                <dd>5 API</dd>
              </div>
            </dl>
          </div>
          <aside class="hero-panel">
            <div class="panel-head">
              <div>
                <p class="panel-kicker">Публичный робот · Comon #131208</p>
                <h2 id="hero-title">Юань Тренд 2-5-15</h2>
              </div>
              <span class="chip chip-live" id="live-chip">Live</span>
            </div>
            <canvas id="hero-spark" width="640" height="220" aria-label="Мини-график доходности"></canvas>
            <div class="panel-metrics">
              <div>
                <span>За 30 дней</span>
                <strong id="hero-m30">+12.3%</strong>
              </div>
              <div>
                <span>За 90 дней</span>
                <strong class="pos" id="hero-m90">+7.2%</strong>
              </div>
              <div>
                <span>Мин. сумма</span>
                <strong id="hero-minsum">30 000 ₽</strong>
              </div>
            </div>
          </aside>
        </div>
        <aside class="robot-log" id="robot-log" aria-hidden="true">
          <p class="robot-log-head"><span class="pulse"></span> robot runtime</p>
          <ol id="robot-log-list"></ol>
        </aside>
      </section>

      <div class="ticker" aria-hidden="true">
        <div class="ticker-track">
          <div class="ticker-group">
            <span>FINAM</span><span>TINKOFF</span><span>BYBIT</span><span>OKX</span><span>BINANCE</span>
            <span>MOEX</span><span>BTC</span><span>ETH</span><span>CNYRUB</span>
          </div>
          <div class="ticker-group">
            <span>FINAM</span><span>TINKOFF</span><span>BYBIT</span><span>OKX</span><span>BINANCE</span>
            <span>MOEX</span><span>BTC</span><span>ETH</span><span>CNYRUB</span>
          </div>
        </div>
      </div>

      <div class="quotes-tape" aria-hidden="true">
        <div class="quotes-tape-track">
          <div class="quotes-tape-group" id="quotes-a"></div>
          <div class="quotes-tape-group" id="quotes-b"></div>
        </div>
      </div>

      <section class="section" id="markets">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Рынки</p>
            <h2>Один подход — две вселенные ликвидности</h2>
            <p>Пишу роботов под реальные стаканы, комиссии и режим торгов, а не под идеальный бэктест.</p>
          </div>
          <div class="cards-2">
            <article class="glass market-card">
              <div class="icon-row">
                <span class="icon">RU</span>
                <span class="tag">Фондовый рынок</span>
              </div>
              <h3>MOEX · акции, фьючерсы, валюта</h3>
              <p>
                Тренд и mean-reversion на ликвидных инструментах: юань, индекс,
                голубые фишки, срочный рынок. Учёт ГО, переноса позиций и расписания сессий.
              </p>
              <ul>
                <li>Фьючерс на юань и валютные пары</li>
                <li>Индексные и товарные фьючерсы</li>
                <li>Роботы через API Финам, Тинькофф Инвестиции и автоследование Comon</li>
              </ul>
            </article>
            <article class="glass market-card">
              <div class="icon-row">
                <span class="icon icon-crypto">Ξ</span>
                <span class="tag">Крипто рынок</span>
              </div>
              <h3>BTC · ETH · альты · перпы</h3>
              <p>
                Алгоритмы под 24/7: тренд на старших ТФ, сетки в диапазоне,
                риск на волатильность и защита от каскадных ликвидаций.
              </p>
              <ul>
                <li>Spot и perpetual на Bybit, OKX, Binance</li>
                <li>Исполнение через API, алерты в Telegram</li>
                <li>Риск в % депозита, а не «на глаз»</li>
              </ul>
            </article>
          </div>
        </div>
      </section>

      <section class="section section-tight" id="venues">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Площадки</p>
            <h2>Пишем торговых роботов под эти API</h2>
            <p>Один алгоритм — разные адаптеры исполнения. Подключаем счёт и гоняем ордера там, где вы торгуете.</p>
          </div>
          <div class="cards-5">
            <article class="glass pad">
              <p class="num">MOEX</p>
              <h3>Финам</h3>
              <p>Trade API, срочный рынок, акции и валюта. Роботы и контуры автоследования Comon.</p>
              <div class="mini-candles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            </article>
            <article class="glass pad">
              <p class="num">MOEX</p>
              <h3>Тинькофф Инвестиции</h3>
              <p>Invest API: акции, облигации, фьючерсы и валюта. Роботы через официальный T-Invest API.</p>
              <div class="mini-candles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            </article>
            <article class="glass pad">
              <p class="num">CEX</p>
              <h3>Bybit</h3>
              <p>Spot и perpetual, стакан, позиции и риск через официальный API.</p>
              <div class="mini-candles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            </article>
            <article class="glass pad">
              <p class="num">CEX</p>
              <h3>OKX</h3>
              <p>Торговые боты под фьючерсы и спот, исполнение и мониторинг 24/7.</p>
              <div class="mini-candles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            </article>
            <article class="glass pad">
              <p class="num">CEX</p>
              <h3>Binance</h3>
              <p>Роботы на ликвидных парах: тренд, сетка, алго-исполнение через API.</p>
              <div class="mini-candles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="stack">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Стек</p>
            <h2>На чём пишем роботов и сервисы</h2>
            <p>Берём API брокера или биржи и собираем вокруг него устойчивый контур: сигнал, риск, исполнение, журнал.</p>
          </div>
          <div class="cards-2 stack-hero">
            <article class="glass pad stack-card">
              <p class="num">Node.js</p>
              <h3>Роботы, шлюзы, realtime</h3>
              <p>
                Быстрый контур вокруг REST и WebSocket: адаптеры к API, ордер-менеджмент,
                алерты, кабинеты и админки для финтех-продуктов.
              </p>
              <ul class="fine-list">
                <li>Адаптеры API: Финам, Тинькофф Инвестиции, Bybit, OKX, Binance</li>
                <li>Стриминг котировок и статусов заявок</li>
                <li>Личные кабинеты, вебхуки, Telegram-боты</li>
              </ul>
            </article>
            <article class="glass pad stack-card">
              <p class="num">Go</p>
              <h3>Нагруженные сервисы и ядро</h3>
              <p>
                Низкая задержка и предсказуемая нагрузка: расчёт сигналов, риск-движок,
                очереди исполнения, фоновые воркеры.
              </p>
              <ul class="fine-list">
                <li>Микросервисы под торговый и расчётный контур</li>
                <li>Высокая пропускная способность API</li>
                <li>Надёжный фон для продуктов 24/7</li>
              </ul>
            </article>
          </div>
          <div class="cards-3 stack-extra">
            <article class="glass pad">
              <p class="num">API</p>
              <h3>Брокеры и биржи</h3>
              <p>Официальные API Финам, Тинькофф Инвестиции, Bybit, OKX и Binance. Без кликеров и серых обходов терминала.</p>
            </article>
            <article class="glass pad">
              <p class="num">Fintech</p>
              <h3>Сервисы для продуктов</h3>
              <p>Бэкенд, биллинг сигналов, кабинеты авторов, витрины стратегий, отчётность и мониторинг.</p>
            </article>
            <article class="glass pad">
              <p class="num">Ops</p>
              <h3>Боевой контур</h3>
              <p>Логи, ретраи, идемпотентность ордеров, деплой и наблюдение — чтобы робот жил как сервис, а не как скрипт.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section section-tight" id="algos">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Продукты</p>
            <h2>Что собираем под ключ</h2>
          </div>
          <div class="cards-3">
            <article class="glass pad">
              <p class="num">01</p>
              <h3>Трендовые роботы</h3>
              <p>Вход по направлению устойчивого движения, выход по цели или стопу. Как в кейсе 2-5-15.</p>
            </article>
            <article class="glass pad">
              <p class="num">02</p>
              <h3>Управление капиталом</h3>
              <p>Фиксированный риск на сделку, дневной лимит убытка, запрет усреднения против системы.</p>
            </article>
            <article class="glass pad">
              <p class="num">03</p>
              <h3>Исполнение и мониторинг</h3>
              <p>Журнал сделок, алерты, исполнение через API Финам, Тинькофф Инвестиции, Bybit, OKX или Binance.</p>
            </article>
          </div>
          <div class="glass pad price-banner" id="price">
            <div>
              <p class="eyebrow">Стоимость и сроки</p>
              <h3>Торговый алгоритм под ключ</h3>
              <p>
                Разработка торгового алгоритма — от 20 000 ₽, сроки от 2 дней
                в зависимости от сложности.
              </p>
            </div>
            <dl class="price-stats">
              <div>
                <dt>От</dt>
                <dd>20 000 ₽</dd>
              </div>
              <div>
                <dt>Срок</dt>
                <dd>от 2 дн.</dd>
              </div>
            </dl>
          </div>
        </div>
      </section>

      <section class="section" id="dashboards">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Дашборды</p>
            <h2>Кабинеты и панели, которые собираем вокруг роботов</h2>
            <p>
              Не только советник. Делаем экраны под трейдинг: PnL, риск, позиции, журнал и кабинеты
              для финтех-продукта. Ниже — примеры интерфейсов.
            </p>
          </div>

          <div class="dash-bento">
            <article class="glass pad dash-screen dash-hero-card">
              <div class="dash-top">
                <div>
                  <p class="panel-kicker">Live desk · Bybit Unified</p>
                  <h3>Торговый стол робота</h3>
                </div>
                <span class="chip chip-live">Live</span>
              </div>
              <div class="dash-kpis">
                <div>
                  <span>Equity</span>
                  <strong>143.55 USDT</strong>
                </div>
                <div>
                  <span>Сегодня</span>
                  <strong class="pos">+1.84%</strong>
                </div>
                <div>
                  <span>Позиция</span>
                  <strong>BTC long</strong>
                </div>
              </div>
              <svg class="dash-spark" viewBox="0 0 320 88" aria-hidden="true">
                <path class="dash-spark-fill" d="M0 70 C20 68 28 62 44 58 C68 50 84 64 108 42 C128 26 148 34 168 22 C196 8 214 28 240 18 C268 6 292 20 320 12 L320 88 L0 88 Z"></path>
                <path class="dash-spark-line" d="M0 70 C20 68 28 62 44 58 C68 50 84 64 108 42 C128 26 148 34 168 22 C196 8 214 28 240 18 C268 6 292 20 320 12"></path>
              </svg>
              <table class="dash-table">
                <thead>
                  <tr>
                    <th>Инструмент</th>
                    <th>Сторона</th>
                    <th>PnL</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>BTCUSDT</td>
                    <td>Long</td>
                    <td class="pos">+0.42%</td>
                  </tr>
                  <tr>
                    <td>CNYRUB FUT</td>
                    <td>Long</td>
                    <td class="pos">+2.10%</td>
                  </tr>
                  <tr>
                    <td>ETHUSDT</td>
                    <td>Flat</td>
                    <td>—</td>
                  </tr>
                </tbody>
              </table>
            </article>

            <article class="glass pad dash-screen">
              <div class="dash-top">
                <div>
                  <p class="panel-kicker">Risk engine</p>
                  <h3>Риск и лимиты</h3>
                </div>
              </div>
              <p class="dash-copy">Дневной стоп, экспозиция, запрет усреднения — на одном экране у автора и у клиента.</p>
              <ul class="dash-meters">
                <li>
                  <span>Дневной убыток <b>32%</b> лимита</span>
                  <i class="meter"><i style="--w:32%"></i></i>
                </li>
                <li>
                  <span>Маржа <b>18%</b></span>
                  <i class="meter meter-blue"><i style="--w:18%"></i></i>
                </li>
                <li>
                  <span>Просадка <b>4.8%</b></span>
                  <i class="meter meter-warn"><i style="--w:24%"></i></i>
                </li>
              </ul>
            </article>

            <article class="glass pad dash-screen">
              <div class="dash-top">
                <div>
                  <p class="panel-kicker">Execution log</p>
                  <h3>Журнал и алерты</h3>
                </div>
              </div>
              <ol class="dash-feed" id="dash-feed">
                <li class="buy"><span>09:41</span> BYBIT · BUY BTCUSDT</li>
                <li class="tp"><span>09:38</span> FINAM · TP CNY +1.2%</li>
                <li class="buy"><span>09:31</span> TINKOFF · BUY SBER</li>
                <li class="sell"><span>09:22</span> OKX · STOP ETHUSDT</li>
                <li class="buy"><span>09:11</span> BINANCE · GRID fill</li>
              </ol>
            </article>
          </div>

          <div class="cards-3 dash-more">
            <article class="glass pad dash-mini">
              <p class="num">Cabinet</p>
              <h3>Кабинет автора Comon / сигналов</h3>
              <p>Подписчики, тариф, кривая стратегии, заявки на автоследование. Витрина, как у публичного кейса.</p>
            </article>
            <article class="glass pad dash-mini">
              <p class="num">Multi</p>
              <h3>Мультибиржа</h3>
              <p>Один экран на Финам, Тинькофф Инвестиции, Bybit, OKX и Binance: балансы, позиции, статус роботов 24/7.</p>
            </article>
            <article class="glass pad dash-mini">
              <p class="num">Fintech</p>
              <h3>Аналитика для продукта</h3>
              <p>Отчёты для клиентов: доходность, комиссии, сделки, выгрузки. Бэкенд и кабинет под ваш бренд.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="case">
        <div class="container">
          <div class="slider-bar">
            <div>
              <p class="eyebrow">Кейсы</p>
              <h2>Живые стратегии</h2>
            </div>
            <div class="slider-nav">
              <button type="button" class="slider-btn" id="slide-prev" aria-label="Предыдущая стратегия">‹</button>
              <div class="slider-dots" role="tablist" aria-label="Стратегии">
                <button type="button" class="slider-dot is-active" data-slide="0">Юань · Comon</button>
                <button type="button" class="slider-dot" data-slide="1" hidden>Юань · Тинькофф</button>
                <button type="button" class="slider-dot" data-slide="1">BTC · тест</button>
              </div>
              <button type="button" class="slider-btn" id="slide-next" aria-label="Следующая стратегия">›</button>
            </div>
          </div>
          <div class="case-slider">
            <div class="case-track" id="case-track">
              <article class="case-slide" id="slide-comon">
          <div class="section-head case-head">
            <div>
              <p class="eyebrow">Кейс · автообновление с Comon</p>
              <h2 id="case-title">Юань Тренд 2-5-15</h2>
              <p class="case-meta">
                Запуск
                <span id="case-start">08.04.2026</span> · источник
                <a id="case-link" href="https://www.comon.ru/strategies/131208/" target="_blank" rel="noopener"
                  >comon.ru/strategies/131208</a
                >
              </p>
            </div>
            <button class="parsed-stamp" id="parsed-stamp" type="button" title="Обновить с Comon">
              Обновить с Comon
            </button>
          </div>

          <div class="metrics" id="case-metrics"></div>

          <div class="chart-wrap glass">
            <div class="chart-toolbar">
              <div>
                <h3>Кривая доходности</h3>
                <p id="chart-caption">Накопленный результат публичной стратегии, %</p>
              </div>
              <div class="pills" id="comon-pills" role="tablist" aria-label="Период графика">
                <button type="button" class="pill is-active" data-range="all">Всё время</button>
                <button type="button" class="pill" data-range="90">90 дней</button>
                <button type="button" class="pill" data-range="30">30 дней</button>
              </div>
            </div>
            <div class="chart-stage">
              <canvas id="equity-chart" width="1100" height="420"></canvas>
              <div class="chart-tip" id="chart-tip" hidden></div>
            </div>
          </div>

          <div class="case-grid">
            <article class="glass pad">
              <h3>Логика робота</h3>
              <p>
                Автоматическая стратегия по фьючерсу на юань. Работает в сторону
                устойчивого движения, характер умеренно-агрессивный.
              </p>
              <div class="rule-row">
                <div>
                  <span>Вход</span>
                  <strong>2%</strong>
                  <em>от депозита</em>
                </div>
                <div>
                  <span>Стоп</span>
                  <strong class="neg">−5%</strong>
                  <em>от депозита</em>
                </div>
                <div>
                  <span>Цель</span>
                  <strong class="pos">+15%</strong>
                  <em>от депозита</em>
                </div>
              </div>
              <ul class="fine-list">
                <li>Если сделка старше 5 дней и прибыль 7–10%, фиксация может быть досрочной.</li>
                <li>При прибыли выше ~10% позиция обычно держится до цели 15%.</li>
                <li>С 01.06.2026 усилена логика тренда: меньше ложных входов в боковике.</li>
              </ul>
            </article>
            <article class="glass pad">
              <h3>Состав и доступ</h3>
              <div class="bars" id="structure-bars"></div>
              <dl class="spec">
                <div>
                  <dt>Профиль риска</dt>
                  <dd id="spec-risk">Агрессивный</dd>
                </div>
                <div>
                  <dt>Категория</dt>
                  <dd id="spec-cat">КСУР, КПУР</dd>
                </div>
                <div>
                  <dt>Тариф</dt>
                  <dd id="spec-tariff">6% годовых от СЧА</dd>
                </div>
                <div>
                  <dt>Лимит стратегии</dt>
                  <dd id="spec-limit">до 50 млн ₽</dd>
                </div>
                <div>
                  <dt>ИТА</dt>
                  <dd id="spec-ita">0.77</dd>
                </div>
                <div>
                  <dt>Позиция сейчас</dt>
                  <dd id="spec-position">шорт фьючерса, кэш ~100%</dd>
                </div>
              </dl>
            </article>
          </div>
              </article>
              <article class="case-slide" id="slide-tinkoff" hidden>
          <div class="section-head case-head">
            <div>
              <p class="eyebrow">Тестовый кейс · Тинькофф Инвестиции API</p>
              <h2 id="tinkoff-title">Юань Тренд 2-5-15 · Тинькофф · тест</h2>
              <p class="case-meta">
                Тот же тренд 2 / −5 / +15 по юаню, пока на тестовом контуре T-Invest API ·
                <a href="https://www.tbank.ru/invest/" target="_blank" rel="noopener">tbank.ru/invest</a>
              </p>
            </div>
            <button class="parsed-stamp" id="tinkoff-stamp" type="button" title="Обновить с Тинькофф">
              Обновить с Тинькофф
            </button>
          </div>

          <div class="metrics" id="tinkoff-metrics"></div>

          <div class="chart-wrap glass">
            <div class="chart-toolbar">
              <div>
                <h3>Кривая баланса</h3>
                <p>Дневная доходность брокерского счёта, %</p>
              </div>
              <div class="pills" id="tinkoff-pills" role="tablist" aria-label="Период графика Тинькофф">
                <button type="button" class="pill is-active" data-range="all">Всё время</button>
                <button type="button" class="pill" data-range="90">90 дней</button>
                <button type="button" class="pill" data-range="30">30 дней</button>
              </div>
            </div>
            <div class="chart-stage">
              <canvas id="tinkoff-chart" width="1100" height="420"></canvas>
              <div class="chart-tip" id="tinkoff-tip" hidden></div>
            </div>
          </div>

          <div class="case-grid">
            <article class="glass pad">
              <h3>Логика робота</h3>
              <p>
                Автоматическая стратегия по юаню через официальный API Тинькофф Инвестиций.
                Вход 2% депозита, стоп −5%, цель +15% — как в публичном кейсе Comon.
              </p>
              <div class="rule-row">
                <div>
                  <span>Вход</span>
                  <strong>2%</strong>
                  <em>от депозита</em>
                </div>
                <div>
                  <span>Стоп</span>
                  <strong class="neg">−5%</strong>
                  <em>от депозита</em>
                </div>
                <div>
                  <span>Цель</span>
                  <strong class="pos">+15%</strong>
                  <em>от депозита</em>
                </div>
              </div>
              <ul class="fine-list">
                <li>Тестовый контур: цифры рабочие, стратегия ещё на прогоне.</li>
                <li>Исполнение через T-Invest API, без кликера терминала.</li>
                <li>В кривую входят операции счёта и ежедневный снимок эквити.</li>
              </ul>
            </article>
            <article class="glass pad">
              <h3>Счёт и позиция</h3>
              <div class="bars" id="tinkoff-bars"></div>
              <dl class="spec">
                <div>
                  <dt>Площадка</dt>
                  <dd>Тинькофф Инвестиции</dd>
                </div>
                <div>
                  <dt>Инструмент</dt>
                  <dd id="tinkoff-instrument">CNY</dd>
                </div>
                <div>
                  <dt>Позиция</dt>
                  <dd id="tinkoff-position">—</dd>
                </div>
                <div>
                  <dt>Средняя</dt>
                  <dd id="tinkoff-avg">—</dd>
                </div>
                <div>
                  <dt>Нереализ. PnL</dt>
                  <dd id="tinkoff-upl">—</dd>
                </div>
                <div>
                  <dt>Оценка счёта</dt>
                  <dd id="tinkoff-equity">—</dd>
                </div>
              </dl>
            </article>
          </div>
              </article>
              <article class="case-slide" id="slide-bybit">
          <div class="section-head case-head">
            <div>
              <p class="eyebrow">Тестовый кейс · пока считаем доходность</p>
              <h2 id="bybit-title">BTC Trend · Bybit · тест</h2>
              <p class="case-meta">
                Тестовый контур BTCUSDT Perp. Цифры рабочие, но стратегия ещё на тесте ·
                <a href="https://www.bybit.com/" target="_blank" rel="noopener">bybit.com</a>
              </p>
            </div>
            <button class="parsed-stamp" id="bybit-stamp" type="button" title="Обновить с Bybit">
              Обновить с Bybit
            </button>
          </div>

          <div class="metrics" id="bybit-metrics"></div>

          <div class="chart-wrap glass">
            <div class="chart-toolbar">
              <div>
                <h3>Кривая баланса</h3>
                <p>Дневная доходность счёта Unified, %</p>
              </div>
              <div class="pills" id="bybit-pills" role="tablist" aria-label="Период графика Bybit">
                <button type="button" class="pill is-active" data-range="all">Всё время</button>
                <button type="button" class="pill" data-range="90">90 дней</button>
                <button type="button" class="pill" data-range="30">30 дней</button>
              </div>
            </div>
            <div class="chart-stage">
              <canvas id="bybit-chart" width="1100" height="420"></canvas>
              <div class="chart-tip" id="bybit-tip" hidden></div>
            </div>
          </div>

          <div class="case-grid">
            <article class="glass pad">
              <h3>Логика робота</h3>
              <p>
                Трендовый робот по бессрочному фьючерсу BTCUSDT на Bybit.
                Входит по направлению движения, режет риск и забирает профит по правилам системы.
              </p>
              <div class="rule-row">
                <div>
                  <span>Площадка</span>
                  <strong>Bybit</strong>
                  <em>Unified API</em>
                </div>
                <div>
                  <span>Инструмент</span>
                  <strong>BTC</strong>
                  <em>USDT Perp</em>
                </div>
                <div>
                  <span>Счёт</span>
                  <strong id="bybit-equity">—</strong>
                  <em>текущий баланс</em>
                </div>
              </div>
              <ul class="fine-list">
                <li>Доходность считается по изменению баланса за каждый день, не по витрине.</li>
                <li>В кривую входят закрытый результат и актуальная оценка счёта.</li>
                <li>Сервер каждый день пишет снимок эквити и подтягивает историю сделок BTC.</li>
              </ul>
            </article>
            <article class="glass pad">
              <h3>Счёт и позиция</h3>
              <div class="bars" id="bybit-bars"></div>
              <dl class="spec">
                <div>
                  <dt>Площадка</dt>
                  <dd>Bybit Unified</dd>
                </div>
                <div>
                  <dt>Инструмент</dt>
                  <dd>BTCUSDT</dd>
                </div>
                <div>
                  <dt>Позиция</dt>
                  <dd id="bybit-position">—</dd>
                </div>
                <div>
                  <dt>Средняя</dt>
                  <dd id="bybit-avg">—</dd>
                </div>
                <div>
                  <dt>Нереализ. PnL</dt>
                  <dd id="bybit-upl">—</dd>
                </div>
                <div>
                  <dt>Доступно</dt>
                  <dd id="bybit-free">—</dd>
                </div>
              </dl>
            </article>
          </div>
              </article>
            </div>
          </div>
        </div>
      </section>

      <?php if ($posts): ?>
      <section class="section home-blog" id="blog">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Блог</p>
            <h2>Свежие статьи</h2>
            <p>Разборы площадок, API и того, как собираем роботов. Обновляется из админки.</p>
          </div>
          <div class="blog-list home-blog-list">
            <?php foreach ($posts as $item): ?>
              <?php $url = quantlab_public_path('blog/' . $item['slug']); ?>
              <article class="glass pad blog-card">
                <?php if (!empty($item['image'])): ?>
                  <a class="blog-card-cover" href="<?= quantlab_h($url) ?>">
                    <img src="<?= quantlab_h((string) $item['image']) ?>" alt="<?= quantlab_h((string) $item['title']) ?>" />
                  </a>
                <?php endif; ?>
                <p class="eyebrow"><?= quantlab_h((string) $item['slug']) ?></p>
                <h3><a href="<?= quantlab_h($url) ?>"><?= quantlab_h((string) $item['title']) ?></a></h3>
                <?php if (!empty($item['excerpt'])): ?>
                  <p><?= quantlab_h((string) $item['excerpt']) ?></p>
                <?php endif; ?>
                <p class="article-meta">
                  <?php if (!empty($item['published_at'])): ?>
                    <time datetime="<?= quantlab_h(substr((string) $item['published_at'], 0, 10)) ?>">
                      <?= quantlab_h(date('d.m.Y', strtotime((string) $item['published_at']) ?: time())) ?>
                    </time>
                  <?php endif; ?>
                  <a href="<?= quantlab_h($url) ?>">Читать</a>
                </p>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="home-blog-more"><a class="btn btn-ghost" href="/blog/">Все статьи</a></p>
        </div>
      </section>
      <?php endif; ?>

      <section class="section" id="process">
        <div class="container">
          <div class="section-head">
            <p class="eyebrow">Процесс</p>
            <h2>От идеи до боевого контура</h2>
          </div>
          <ol class="steps">
            <li>
              <h3>Гипотеза и данные</h3>
              <p>Инструмент, сессия, издержки. Смотрим, есть ли устойчивое преимущество, а не красивая кривая.</p>
            </li>
            <li>
              <h3>Правила и риск</h3>
              <p>Формализуем вход, выход, размер. Жёсткий стоп и цель — как в модели 2-5-15.</p>
            </li>
            <li>
              <h3>Код и прогон</h3>
              <p>Код на Node.js и Go, прогон на истории, затем бумага или маленький боевой счёт через API.</p>
            </li>
            <li>
              <h3>Публикация</h3>
              <p>Финам / Comon, Тинькофф Инвестиции, Bybit, OKX, Binance — подключаем API и мониторинг.</p>
            </li>
          </ol>
        </div>
      </section>

      <section class="section" id="contact">
        <div class="container contact-grid">
          <div>
            <p class="eyebrow">Контакт</p>
            <h2>Нужен робот или сервис под финтех-продукт</h2>
            <p class="lead">
              Опишите площадку и задачу. Соберём робота под Финам, Тинькофф Инвестиции, Bybit, OKX или Binance — и сервисы вокруг продукта.
            </p>
            <p class="price-note">
              Разработка торгового алгоритма — <strong>от 20 000 ₽</strong>,
              сроки — <strong>от 2 дней</strong> в зависимости от сложности.
            </p>
            <div class="contact-direct">
              <a class="tg" href="mailto:<?= quantlab_h(quantlab_site_email()) ?>">
                <?= quantlab_h(quantlab_site_email()) ?>
              </a>
              <a class="tg" href="https://t.me/where_is_Lebowskis_money" target="_blank" rel="noopener">
                Telegram · рынок и алгоритмы
              </a>
            </div>
          </div>
          <form class="glass pad form" id="lead-form" action="/api/lead.php" method="post">
            <label class="hp" aria-hidden="true">
              Сайт
              <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </label>
            <label>
              Имя
              <input type="text" name="name" required placeholder="Как к вам обращаться" />
            </label>
            <label>
              Telegram или email
              <input type="text" name="contact" required placeholder="@username или mail@mail.ru" />
            </label>
            <label>
              Рынок
              <select name="market">
                <option value="finam">Финам / MOEX</option>
                <option value="tinkoff">Тинькофф Инвестиции</option>
                <option value="bybit">Bybit</option>
                <option value="okx">OKX</option>
                <option value="binance">Binance</option>
                <option value="multi">Несколько площадок</option>
                <option value="fintech">Сервис для финтех-продукта</option>
              </select>
            </label>
            <label>
              Задача
              <textarea name="message" rows="4" required placeholder="Инструмент, депозит, что должен делать робот"></textarea>
            </label>
            <button class="btn" type="submit">Отправить заявку</button>
            <p class="privacy-agree">
              Отправляя заявку, вы соглашаетесь с
              <a href="#privacy" data-privacy>политикой конфиденциальности</a>.
            </p>
            <p class="form-note" id="form-note" <?= $formSent ? '' : 'hidden' ?>>Скоро мы с вами свяжемся</p>
          </form>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-top">
          <div>
            <a class="logo" href="#top">AM Quant<span>Lab</span></a>
            <p>Роботы на Node.js и Go. API Финам, Тинькофф Инвестиции, Bybit, OKX, Binance.</p>
          </div>
          <span class="sys-status"><span class="pulse"></span> systems online</span>
        </div>
        <p class="footer-links">
          <a href="/">Главная</a>
          <a href="/blog/">Блог</a>
          <a href="#case">Кейсы</a>
          <a href="#contact">Контакт</a>
          <a href="mailto:<?= quantlab_h(quantlab_site_email()) ?>"><?= quantlab_h(quantlab_site_email()) ?></a>
          <a href="#privacy" data-privacy>Политика конфиденциальности</a>
        </p>
        <p class="disclaimer">
          © <?= quantlab_h($year) ?> AM QuantLab. Материал не является индивидуальной инвестиционной рекомендацией. Доходность в прошлом
          не гарантирует результат в будущем. Цифры кейса взяты из публичной страницы Comon
          и могут отличаться от чистого результата счёта после комиссий и проскальзывания.
        </p>
      </div>
    </footer>

    <script src="/js/data.js"></script>
    <script src="/js/app.js"></script>
    <script src="/js/motion.js"></script>
    <script src="/js/privacy.js"></script>
  </body>
</html>
