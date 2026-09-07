<?php

declare(strict_types=1);

if (!function_exists('quantlab_render_start')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';
}

http_response_code(404);
quantlab_render_start([
    'title' => 'Страница не найдена — AM QuantLab',
    'description' => 'Такой страницы нет. Вернитесь на главную или в блог AM QuantLab.',
    'canonical' => quantlab_site_url() . quantlab_request_path(),
    'robots' => 'noindex,nofollow',
    'body_class' => 'page-inner page-404',
]);
?>
      <div class="container">
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
        ]) ?>
        <p class="eyebrow">404</p>
        <h1>Такой страницы нет</h1>
        <p class="lead">Ссылка устарела или адрес написан с ошибкой. Дублей и лишних каноникалов здесь нет — только рабочие URL.</p>
        <div class="hero-actions">
          <a class="btn" href="/">На главную</a>
          <a class="btn btn-ghost" href="/blog/">В блог</a>
        </div>
      </div>
<?php
quantlab_render_end();
