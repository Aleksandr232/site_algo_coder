<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$status = quantlab_db_status();
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Установка MySQL — AM QuantLab</title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="/css/styles.css" />
  </head>
  <body class="page-inner page-admin">
    <div class="noise" aria-hidden="true"></div>
    <main class="page-main">
      <div class="container admin-login">
        <p class="eyebrow">Установка</p>
        <h1>База MySQL</h1>
        <?php if ($status['ready']): ?>
          <p class="form-note" style="display:block">Таблицы созданы. Статьи и заявки пишутся в MySQL.</p>
          <p>Есть: <?= quantlab_h(implode(', ', $status['tables'])) ?></p>
          <p><a class="btn" href="/admin/">В админку</a></p>
        <?php elseif ($status['ok']): ?>
          <p class="form-note" style="display:block">Подключение есть, но не все таблицы: <?= quantlab_h(implode(', ', $status['tables']) ?: 'нет') ?></p>
          <p><a class="btn" href="/install.php">Создать ещё раз</a></p>
        <?php else: ?>
          <p class="form-note" style="display:block"><?= quantlab_h($status['error']) ?></p>
          <p class="lead">
            Откройте эту страницу на хостинге, не на локальном компьютере.
            Базу <code><?= quantlab_h(quantlab_env('MYSQL_DATABASE')) ?></code> сначала создайте в панели Beget — сайт сам добавит таблицы.
          </p>
          <p>Проверьте, что на сервере лежит файл <code>.env</code> с MYSQL_HOST=localhost.</p>
          <p><a class="btn" href="/install.php">Повторить</a></p>
        <?php endif; ?>
      </div>
    </main>
  </body>
</html>
