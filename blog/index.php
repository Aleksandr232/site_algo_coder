<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
$baseCanonical = quantlab_enforce_canonical('blog');
$all = quantlab_blog_published();
$perPage = 9;
$total = count($all);
$pages = max(1, (int) ceil($total / $perPage));
$rawPage = (string) ($_GET['page'] ?? '1');
$page = (int) $rawPage;

if (isset($_GET['page']) && ($rawPage === '1' || $page < 1)) {
    header('Location: /blog/', true, 301);
    exit;
}
if ($page > $pages) {
    header('Location: ' . quantlab_blog_page_url($pages), true, 302);
    exit;
}

$posts = array_slice($all, ($page - 1) * $perPage, $perPage);
$canonical = $page > 1 ? rtrim($baseCanonical, '/') . '/?page=' . $page : $baseCanonical;

$list = [];
foreach ($posts as $i => $item) {
    $list[] = [
        '@type' => 'ListItem',
        'position' => (($page - 1) * $perPage) + $i + 1,
        'url' => quantlab_abs_url('blog/' . $item['slug']),
        'name' => $item['title'],
    ];
}
$extra = quantlab_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $page > 1 ? 'Блог AM QuantLab, страница ' . $page : 'Блог AM QuantLab',
    'description' => 'Статьи о торговых роботах, API бирж и финтех-сервисах.',
    'inLanguage' => 'ru-RU',
    'url' => $canonical,
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'AM QuantLab', 'url' => quantlab_abs_url('/')],
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => $list,
    ],
]);
$site = rtrim(quantlab_site_url(), '/');
if ($page > 1) {
    $extra .= '<link rel="prev" href="' . quantlab_h($site . quantlab_blog_page_url($page - 1)) . '" />';
}
if ($page < $pages) {
    $extra .= '<link rel="next" href="' . quantlab_h($site . quantlab_blog_page_url($page + 1)) . '" />';
}

quantlab_render_start([
    'title' => $page > 1 ? 'Блог, страница ' . $page . ' — AM QuantLab' : 'Блог — AM QuantLab',
    'description' => $page > 1
        ? 'Статьи AM QuantLab о торговых роботах и финтехе, страница ' . $page . '.'
        : 'Статьи AM QuantLab о торговых роботах, API Финам, Тинькофф Инвестиции, Bybit, OKX и Binance, алгоритмах и финтех-сервисах.',
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

        <?php if (!$all): ?>
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
          <?php if ($pages > 1): ?>
            <nav class="blog-pager" aria-label="Страницы блога">
              <?php if ($page > 1): ?>
                <a href="<?= quantlab_h(quantlab_blog_page_url($page - 1)) ?>">Назад</a>
              <?php else: ?>
                <span class="is-off">Назад</span>
              <?php endif; ?>
              <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === $page): ?>
                  <span class="is-current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                  <a href="<?= quantlab_h(quantlab_blog_page_url($i)) ?>"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($page < $pages): ?>
                <a href="<?= quantlab_h(quantlab_blog_page_url($page + 1)) ?>">Дальше</a>
              <?php else: ?>
                <span class="is-off">Дальше</span>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
<?php
quantlab_render_end();
