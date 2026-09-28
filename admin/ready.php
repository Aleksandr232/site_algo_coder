<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    $slug = (string) ($_POST['slug'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if (quantlab_is_slug($slug)) {
        if ($action === 'delete') {
            quantlab_ready_delete($slug);
        } elseif ($action === 'hide') {
            quantlab_ready_set_status($slug, 'hidden');
        } elseif ($action === 'show') {
            quantlab_ready_set_status($slug, 'visible');
        } elseif ($action === 'up' || $action === 'down') {
            quantlab_ready_move($slug, $action);
        }
    }
    header('Location: /admin/ready.php', true, 302);
    exit;
}

$items = quantlab_ready_all();
$venues = quantlab_ready_venues();
$categories = quantlab_ready_categories();
quantlab_admin_start('Продукты — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Продукты', 'path' => '/admin/ready.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Продукты</h1>
            <p class="lead">Разделы «Роботы» и «Утилиты MQL4/MQL5». Для MQL вместо заявки вставляется HTML-виджет покупки.</p>
            <?= quantlab_admin_storage_note() ?>
            <?= quantlab_admin_mail_note() ?>
          </div>
          <a class="btn" href="/admin/ready-edit.php">Новый продукт</a>
        </div>

        <?php if (!$items): ?>
          <div class="glass pad empty-blog">
            <p>Продуктов нет. Добавьте название, раздел, описание, цену и фото — карточка появится на сайте.</p>
          </div>
        <?php else: ?>
          <div class="admin-table glass">
            <table>
              <thead>
                <tr>
                  <th></th>
                  <th>Название</th>
                  <th>Раздел</th>
                  <th>Цена</th>
                  <th>Статус</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <?php if ($item['image'] !== ''): ?>
                        <img class="admin-thumb" src="<?= quantlab_h($item['image']) ?>" alt="" />
                      <?php else: ?>
                        <span class="field-hint">нет фото</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong><?= quantlab_h($item['title']) ?></strong><br />
                      <span class="field-hint"><?= quantlab_h($venues[$item['venue']] ?? $item['venue']) ?></span>
                    </td>
                    <td><?= quantlab_h($categories[$item['category'] ?? 'robot'] ?? 'Роботы') ?></td>
                    <td><?= quantlab_h(quantlab_ready_price_label((string) $item['price'])) ?></td>
                    <td>
                      <span class="badge <?= $item['status'] === 'visible' ? 'badge-ok' : 'badge-warn' ?>">
                        <?= $item['status'] === 'visible' ? 'на сайте' : 'скрыт' ?>
                      </span>
                    </td>
                    <td class="admin-actions">
                      <a href="/admin/ready-edit.php?slug=<?= quantlab_h($item['slug']) ?>">Править</a>
                      <?php if ($item['status'] === 'visible'): ?>
                        <a href="<?= quantlab_h(quantlab_ready_url($item['slug'])) ?>" target="_blank" rel="noopener">Открыть</a>
                      <?php endif; ?>
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="slug" value="<?= quantlab_h($item['slug']) ?>" />
                        <input type="hidden" name="action" value="up" />
                        <button type="submit" class="linkish">↑</button>
                      </form>
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="slug" value="<?= quantlab_h($item['slug']) ?>" />
                        <input type="hidden" name="action" value="down" />
                        <button type="submit" class="linkish">↓</button>
                      </form>
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="slug" value="<?= quantlab_h($item['slug']) ?>" />
                        <input type="hidden" name="action" value="<?= $item['status'] === 'visible' ? 'hide' : 'show' ?>" />
                        <button type="submit" class="linkish"><?= $item['status'] === 'visible' ? 'Скрыть' : 'Показать' ?></button>
                      </form>
                      <form method="post" onsubmit="return confirm('Удалить продукт с витрины?');">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="slug" value="<?= quantlab_h($item['slug']) ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <button type="submit" class="linkish">Удалить</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
<?php
quantlab_admin_end();
