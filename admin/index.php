<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    quantlab_csrf_check();
    $slug = (string) ($_POST['slug'] ?? '');
    if (quantlab_is_slug($slug)) {
        quantlab_blog_delete($slug);
    }
    header('Location: /admin/', true, 302);
    exit;
}

$posts = quantlab_blog_all();
quantlab_admin_start('Статьи — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Статьи</h1>
            <p class="lead">Публикуете — появляется адрес /blog/слаг/, хлебные крошки и строка в sitemap.</p>
            <?= quantlab_admin_storage_note() ?>
          </div>
          <a class="btn" href="/admin/edit.php">Новая статья</a>
          <a class="btn btn-ghost" href="/admin/strategies.php">Стратегии</a>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
          <p class="form-note" style="display:block">Пост удалён.</p>
        <?php endif; ?>
        <?php if (!$posts): ?>
          <div class="glass pad empty-blog">
            <p>Статей ещё нет. Создайте первую — слаг соберётся из заголовка сразу.</p>
          </div>
        <?php else: ?>
          <div class="admin-table glass">
            <table>
              <thead>
                <tr>
                  <th></th>
                  <th>Заголовок</th>
                  <th>Слаг</th>
                  <th>Статус</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($posts as $item): ?>
                  <tr>
                    <td>
                      <?php if (!empty($item['image'])): ?>
                        <img class="admin-thumb" src="<?= quantlab_h($item['image']) ?>" alt="" />
                      <?php endif; ?>
                    </td>
                    <td><?= quantlab_h($item['title']) ?></td>
                    <td><code>/blog/<?= quantlab_h($item['slug']) ?>/</code></td>
                    <td>
                      <span class="badge <?= $item['status'] === 'published' ? 'badge-ok' : 'badge-warn' ?>">
                        <?= $item['status'] === 'published' ? 'опубликована' : 'черновик' ?>
                      </span>
                    </td>
                    <td class="admin-actions">
                      <a href="/admin/edit.php?slug=<?= quantlab_h($item['slug']) ?>">Править</a>
                      <?php if ($item['status'] === 'published'): ?>
                        <a href="<?= quantlab_h(quantlab_public_path('blog/' . $item['slug'])) ?>" target="_blank" rel="noopener">Открыть</a>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('Удалить статью?');">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="slug" value="<?= quantlab_h($item['slug']) ?>" />
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
