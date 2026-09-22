<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

$canonical = quantlab_enforce_canonical('privacy');
$date = quantlab_offer_date();
$inn = quantlab_inn();

$extra = quantlab_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'Политика конфиденциальности AM QuantLab',
    'description' => 'Как AM QuantLab обрабатывает персональные данные заявок, писем и онлайн-оплаты.',
    'inLanguage' => 'ru-RU',
    'dateModified' => '2026-09-23',
    'url' => $canonical,
    'isPartOf' => ['@id' => quantlab_org_id()],
]);

quantlab_render_start([
    'title' => 'Политика конфиденциальности — AM QuantLab',
    'description' => 'Политика конфиденциальности AM QuantLab: какие данные собираем в заявках и при онлайн-оплате, зачем храним и как удалить. ИНН ' . $inn . '.',
    'keywords' => 'политика конфиденциальности, персональные данные, AM QuantLab',
    'canonical' => $canonical,
    'body_class' => 'page-inner page-legal',
    'extra_head' => $extra,
]);
?>
      <article class="container article-page">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Конфиденциальность', 'path' => '/privacy/'],
        ]) ?>
        <p class="eyebrow">Документ</p>
        <h1>Политика конфиденциальности</h1>
        <p class="lead">Как AM QuantLab обрабатывает данные посетителей, заявок и онлайн-оплаты.</p>
        <p class="article-meta">
          <span>Редакция от <?= quantlab_h($date) ?></span>
          <span>ИНН <?= quantlab_h($inn) ?></span>
        </p>

        <?php quantlab_render_legal_requisites(); ?>

        <div class="prose">
          <?php quantlab_render_privacy_prose(); ?>
        </div>
      </article>
<?php
quantlab_render_end();
