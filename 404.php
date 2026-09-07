<?php

declare(strict_types=1);

if (!function_exists('quantlab_render_start')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';
}

http_response_code(404);
header('Cache-Control: no-store');
quantlab_render_start([
    'title' => 'Страница не найдена — AM QuantLab',
    'description' => 'Такой страницы нет. Сейчас откроется главная AM QuantLab.',
    'canonical' => quantlab_abs_url('/'),
    'robots' => 'noindex,nofollow',
    'body_class' => 'page-inner page-404',
]);
?>
      <div class="container page-404-box">
        <p class="eyebrow">404</p>
        <h1>Такой страницы нет</h1>
        <p class="lead">Адрес ошибочный или страница удалена. Через <strong id="home-count">5</strong> сек откроется главная.</p>
        <div class="hero-actions">
          <a class="btn" href="/">На главную</a>
          <a class="btn btn-ghost" href="/blog/">В блог</a>
        </div>
      </div>
      <script>
        (function () {
          var left = 5;
          var node = document.getElementById("home-count");
          var tick = window.setInterval(function () {
            left -= 1;
            if (node) node.textContent = String(Math.max(left, 0));
            if (left <= 0) {
              window.clearInterval(tick);
              window.location.replace("/");
            }
          }, 1000);
        })();
      </script>
<?php
quantlab_render_end();
