<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

$canonical = quantlab_enforce_canonical('offer');
$date = quantlab_offer_date();
$inn = quantlab_inn();

$extra = quantlab_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'Публичная оферта AM QuantLab',
    'description' => 'Публичная оферта на разработку торговых роботов, готовые алгоритмы и финтех-сервисы. Нужна для онлайн-оплаты.',
    'inLanguage' => 'ru-RU',
    'dateModified' => '2026-09-23',
    'url' => $canonical,
    'isPartOf' => ['@id' => quantlab_org_id()],
    'about' => ['@id' => quantlab_org_id()],
    'mentions' => [
        'ИНН ' . $inn,
        'онлайн-оплата',
        'публичная оферта',
    ],
]);

quantlab_render_start([
    'title' => 'Публичная оферта — AM QuantLab',
    'description' => 'Публичная оферта AM QuantLab на разработку торговых роботов и финтех-сервисов. Условия заказа, оплаты, передачи результата и возврата. ИНН ' . $inn . '.',
    'keywords' => 'публичная оферта, оферта AM QuantLab, онлайн оплата, договор на разработку торгового робота',
    'canonical' => $canonical,
    'body_class' => 'page-inner page-legal',
    'extra_head' => $extra,
]);
?>
      <article class="container article-page">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Оферта', 'path' => '/offer/'],
        ]) ?>
        <p class="eyebrow">Документ</p>
        <h1>Публичная оферта</h1>
        <p class="lead">Договор на разработку торговых роботов, готовые алгоритмы и финтех-сервисы. Оплата на сайте означает, что вы приняли эти условия.</p>
        <p class="article-meta">
          <span>Редакция от <?= quantlab_h($date) ?></span>
          <span>ИНН <?= quantlab_h($inn) ?></span>
        </p>

        <?php quantlab_render_legal_requisites(); ?>

        <div class="prose">
          <?php quantlab_render_offer_prose(); ?>
        </div>
      </article>
<?php
quantlab_render_end();
