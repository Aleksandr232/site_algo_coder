<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
$canonical = quantlab_enforce_canonical('blog');
$posts = quantlab_blog_published();

$list = [];
foreach ($posts as $i => $item) {
    $list[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'url' => quantlab_abs_url('blog/' . $item['slug']),
        'name' => $item['title'],
    ];
}
$extra = quantlab_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Блог AM QuantLab',
    'description' => 'Статьи о торговых роботах, API бирж и финтех-сервисах.',
    'inLanguage' => 'ru-RU',
    'url' => $canonical,
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'AM QuantLab', 'url' => quantlab_abs_url('/')],
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => $list,
    ],
]);

quantlab_render_start([
    'title' => 'Блог — AM QuantLab',
    'description' => 'Статьи AM QuantLab о торговых роботах, API Финам, Bybit, OKX и Binance, алгоритмах и финтех-сервисах.',
    'canonical' => $canonical,
    'active' => 'blog',
    'body_class' => 'page-inner page-blog',
    'extra_head' => $extra,
]);
?>
      <div class="container">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Блог', 'path' => '/blog/'],
        ]) ?>
        <p class="eyebrow">Блог</p>
        <h1>Статьи про роботов и финтех</h1>
        <p class="lead">Разбираем площадки, API и то, как собираем алгоритмы. Каждая статья — отдельный адрес со слагом.</p>

        <?php if (!$posts): ?>
          <div class="glass pad empty-blog">
            <p>Пока нет опубликованных материалов.</p>
          </div>
        <?php else: ?>
          <div class="blog-list">
            <?php foreach ($posts as $item): ?>
              <?php $url = quantlab_public_path('blog/' . $item['slug']); ?>
              <article class="glass pad blog-card">
                <?php if (!empty($item['image'])): ?>
                  <a class="blog-card-cover" href="<?= quantlab_h($url) ?>">
                    <img src="<?= quantlab_h($item['image']) ?>" alt="<?= quantlab_h($item['title']) ?>" />
                  </a>
                <?php endif; ?>
                <p class="eyebrow"><?= quantlab_h($item['slug']) ?></p>
                <h2><a href="<?= quantlab_h($url) ?>"><?= quantlab_h($item['title']) ?></a></h2>
                <?php if (!empty($item['excerpt'])): ?>
                  <p><?= quantlab_h($item['excerpt']) ?></p>
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
        <?php endif; ?>
      </div>
<?php
quantlab_render_end();
