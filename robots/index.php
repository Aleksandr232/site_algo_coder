<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
$canonical = quantlab_enforce_canonical('robots');
$items = quantlab_ready_visible();

$list = [];
foreach ($items as $i => $item) {
    $list[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'url' => quantlab_abs_url('robots/' . $item['slug']),
        'name' => $item['title'],
    ];
}
$extra = quantlab_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Продукты — роботы и утилиты MQL — AM QuantLab',
    'description' => 'Каталог продуктов AM QuantLab: готовые роботы и утилиты MQL4/MQL5. Цена, заявка или виджет покупки.',
    'inLanguage' => 'ru-RU',
    'url' => $canonical,
    'isPartOf' => ['@id' => quantlab_org_id()],
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => $list,
    ],
]);

quantlab_render_start([
    'title' => 'Продукты — роботы и утилиты MQL4/MQL5 — AM QuantLab',
    'description' => 'Продукты AM QuantLab: готовые роботы под Финам, Тинькофф, Bybit, OKX и Binance и утилиты MQL4/MQL5. Цена на странице, заявка или виджет покупки.',
    'keywords' => 'готовые торговые роботы, утилиты mql5, купить советник mql4, робот для мосбиржи',
    'canonical' => $canonical,
    'active' => 'robots',
    'body_class' => 'page-inner page-ready-list',
    'extra_head' => $extra,
]);
?>
      <div class="container">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Продукты', 'path' => '/robots/'],
        ]) ?>
        <p class="eyebrow">Каталог</p>
        <h1>Продукты</h1>
        <p class="lead">Два раздела: роботы с заявкой и утилиты MQL4/MQL5. Для MQL на странице стоит виджет покупки вместо формы.</p>

        <?php if (!$items): ?>
          <div class="glass pad empty-blog">
            <p>Пока нет опубликованных продуктов. Можно оставить заявку на кастомную разработку на <a href="/#contact">главной</a>.</p>
          </div>
        <?php else: ?>
          <?php quantlab_render_ready_catalog($items, true); ?>
        <?php endif; ?>
      </div>
<?php
quantlab_render_end();
