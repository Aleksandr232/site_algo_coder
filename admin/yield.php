<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$slug = quantlab_yield_slug((string) ($_GET['slug'] ?? 'main'));
$row = quantlab_yield_load($slug);
$feeds = quantlab_yield_feeds();
$postUrl = quantlab_yield_post_url($slug);
$getUrl = quantlab_yield_get_url($slug);

quantlab_admin_start('Доходность — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Доходность', 'path' => '/admin/yield.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Доходность для графика</h1>
            <p class="lead">Робот шлёт POST на URL ниже. Точки пишутся в таблицу. GET отдаёт серию, из неё потом рисуем кривую на сайте.</p>
          </div>
          <a class="btn btn-ghost" href="/admin/">К статьям</a>
        </div>

        <div class="glass pad" style="margin-bottom:20px">
          <p class="eyebrow">В робота</p>
          <p>POST, ритм каждые N минут или раз в день по Москве. Кнопка «Отправить» — сразу.</p>
          <p><code><?= quantlab_h($postUrl) ?></code></p>
          <p class="form-note" style="display:block">GET для графика, без токена: <code><?= quantlab_h($getUrl) ?></code></p>
          <p>Документация: <a href="/api/yield/">/api/yield/</a></p>
        </div>

        <?php if ($feeds): ?>
          <div class="admin-table glass" style="margin-bottom:20px">
            <table>
              <thead>
                <tr>
                  <th>Слаг</th>
                  <th>День</th>
                  <th>Доходность</th>
                  <th>Equity</th>
                  <th>Обновлено</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($feeds as $feed): ?>
                  <tr>
                    <td><a href="/admin/yield.php?slug=<?= quantlab_h((string) $feed['slug']) ?>"><?= quantlab_h((string) $feed['slug']) ?></a></td>
                    <td><?= quantlab_h((string) ($feed['day'] ?? '')) ?></td>
                    <td><?= quantlab_h(number_format((float) ($feed['return_percent'] ?? 0), 2, '.', ' ')) ?>%</td>
                    <td><?= quantlab_h(number_format((float) ($feed['equity'] ?? 0), 2, '.', ' ')) ?></td>
                    <td><?= quantlab_h((string) ($feed['updated_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="admin-table glass">
          <table>
            <thead>
              <tr>
                <th>Дата</th>
                <th>Equity</th>
                <th>Balance</th>
                <th>Доходность %</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$row['points']): ?>
                <tr><td colspan="4">Пока пусто. Когда робот пришлёт POST, здесь появятся дни для графика.</td></tr>
              <?php else: ?>
                <?php foreach ($row['points'] as $point): ?>
                  <tr>
                    <td><?= quantlab_h((string) $point['date']) ?></td>
                    <td><?= quantlab_h(number_format((float) $point['equity'], 2, '.', ' ')) ?></td>
                    <td><?= quantlab_h(number_format((float) ($point['balance'] ?? $point['equity']), 2, '.', ' ')) ?></td>
                    <td><?= quantlab_h(number_format((float) $point['returnPercent'], 2, '.', ' ')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<?php
quantlab_admin_end();
