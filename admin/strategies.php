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
            quantlab_strategy_delete($slug);
        } elseif ($action === 'hide') {
            quantlab_strategy_set_status($slug, 'hidden');
        } elseif ($action === 'show') {
            quantlab_strategy_set_status($slug, 'visible');
        } elseif ($action === 'up' || $action === 'down') {
            quantlab_strategy_move($slug, $action);
        }
    }
    header('Location: /admin/strategies.php', true, 302);
    exit;
}

$items = quantlab_strategies_all();
$venues = quantlab_strategy_venues();
quantlab_admin_start('Стратегии — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Стратегии', 'path' => '/admin/strategies.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Стратегии</h1>
            <p class="lead">Слайдер на главной. Пока площадки Comon и Bybit. Скрытая не показывается посетителю.</p>
            <?= quantlab_admin_storage_note() ?>
          </div>
          <a class="btn" href="/admin/strategy-edit.php">Новая стратегия</a>
        </div>

        <?php if (!$items): ?>
          <div class="glass pad empty-blog">
            <p>Стратегий нет. Добавьте Comon по ID или кейс Bybit.</p>
          </div>
        <?php else: ?>
          <div class="admin-table glass">
            <table>
              <thead>
                <tr>
                  <th>Название</th>
                  <th>Площадка</th>
                  <th>Статус</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <strong><?= quantlab_h($item['title']) ?></strong><br />
                      <code><?= quantlab_h($item['dot']) ?></code>
                      <?php if ($item['venue'] === 'comon' && $item['comon_id'] !== ''): ?>
                        <br /><span class="field-hint">Comon ID <?= quantlab_h($item['comon_id']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= quantlab_h($venues[$item['venue']] ?? $item['venue']) ?></td>
                    <td>
                      <span class="badge <?= $item['status'] === 'visible' ? 'badge-ok' : 'badge-warn' ?>">
                        <?= $item['status'] === 'visible' ? 'на сайте' : 'скрыта' ?>
                      </span>
                    </td>
                    <td class="admin-actions">
                      <a href="/admin/strategy-edit.php?slug=<?= quantlab_h($item['slug']) ?>">Править</a>
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
                      <form method="post" onsubmit="return confirm('Удалить стратегию с сайта?');">
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
