<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_boot();
if (quantlab_admin_logged_in()) {
    header('Location: /admin/', true, 302);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    if (quantlab_admin_login((string) ($_POST['password'] ?? ''))) {
        header('Location: /admin/', true, 302);
        exit;
    }
    $error = 'Неверный пароль';
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Вход в админку — AM QuantLab</title>
    <meta name="robots" content="noindex,nofollow" />
    <?php quantlab_head_verification(); ?>
    <link rel="stylesheet" href="/css/styles.css" />
  </head>
  <body class="page-inner page-admin">
    <div class="noise" aria-hidden="true"></div>
    <main class="page-main">
      <div class="container admin-login">
        <p class="eyebrow">Админка</p>
        <h1>Вход</h1>
        <form class="glass pad form" method="post">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password" />
          </label>
          <?php if ($error): ?>
            <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
          <?php endif; ?>
          <button class="btn" type="submit">Войти</button>
        </form>
      </div>
    </main>
  </body>
</html>
