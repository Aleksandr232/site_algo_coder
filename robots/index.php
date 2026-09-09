<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
$canonical = quantlab_enforce_canonical('robots');
$items = quantlab_ready_visible();
$venues = quantlab_ready_venues();

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
    'name' => 'Готовые торговые роботы — AM QuantLab',
    'description' => 'Каталог готовых торговых роботов AM QuantLab. Описание, цена и заявка на подключение.',
    'inLanguage' => 'ru-RU',
    'url' => $canonical,
    'isPartOf' => ['@id' => quantlab_org_id()],
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => $list,
    ],
]);

quantlab_render_start([
    'title' => 'Готовые торговые роботы — AM QuantLab',
    'description' => 'Готовые торговые роботы AM QuantLab под Финам, Тинькофф Инвестиции, Bybit, OKX и Binance. Цена на странице, заявка уходит на почту.',
    'keywords' => 'готовые торговые роботы, купить торгового робота, робот для мосбиржи, робот bybit',
    'canonical' => $canonical,
    'active' => 'robots',
    'body_class' => 'page-inner page-ready-list',
    'extra_head' => $extra,
]);
?>
      <div class="container">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Роботы', 'path' => '/robots/'],
        ]) ?>
        <p class="eyebrow">Каталог</p>
        <h1>Готовые торговые роботы</h1>
        <p class="lead">У каждого робота своя страница: описание, цена и форма заявки. Письмо приходит нам на почту.</p>

        <?php if (!$items): ?>
          <div class="glass pad empty-blog">
            <p>Пока нет опубликованных роботов. Можно оставить заявку на кастомную разработку на <a href="/#contact">главной</a>.</p>
          </div>
        <?php else: ?>
          <div class="ready-grid">
            <?php foreach ($items as $robot): ?>
              <?php $url = quantlab_ready_url($robot['slug']); ?>
              <article class="glass pad ready-card">
                <?php if ($robot['image'] !== ''): ?>
                  <a class="ready-card-cover" href="<?= quantlab_h($url) ?>">
                    <img src="<?= quantlab_h($robot['image']) ?>" alt="<?= quantlab_h($robot['title']) ?>" />
                  </a>
                <?php else: ?>
                  <a class="ready-card-cover ready-card-cover-empty" href="<?= quantlab_h($url) ?>" aria-hidden="true"></a>
                <?php endif; ?>
                <p class="eyebrow"><?= quantlab_h($venues[$robot['venue']] ?? $robot['venue']) ?></p>
                <h2><a href="<?= quantlab_h($url) ?>"><?= quantlab_h($robot['title']) ?></a></h2>
                <p><?= quantlab_h($robot['description']) ?></p>
                <div class="ready-card-foot">
                  <strong class="ready-price"><?= quantlab_h($robot['price']) ?></strong>
                  <a class="btn" href="<?= quantlab_h($url) ?>#order">Оставить заявку</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
<?php
quantlab_render_end();
