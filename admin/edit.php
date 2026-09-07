<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$currentSlug = trim((string) ($_GET['slug'] ?? ''));
$post = $currentSlug !== '' ? quantlab_blog_load($currentSlug) : null;
if ($currentSlug !== '' && !$post) {
    http_response_code(404);
    echo 'Статья не найдена';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete' && !empty($post['slug'])) {
        quantlab_blog_delete($post['slug']);
        header('Location: /admin/?deleted=1', true, 302);
        exit;
    }
    try {
        $image = quantlab_blog_handle_image(
            $post['image'] ?? null,
            $_FILES['image'] ?? [],
            !empty($_POST['remove_image'])
        );
        $saved = quantlab_blog_save([
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'excerpt' => $_POST['excerpt'] ?? '',
            'body' => $_POST['body'] ?? '',
            'image' => $image,
            'keywords' => $_POST['keywords'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_description' => $_POST['seo_description'] ?? '',
            'status' => !empty($_POST['published']) ? 'published' : 'draft',
        ], $post['slug'] ?? null);
        header('Location: /admin/edit.php?slug=' . rawurlencode($saved['slug']) . '&saved=1', true, 302);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $post = array_merge($post ?: [], $_POST);
        $post['status'] = !empty($_POST['published']) ? 'published' : 'draft';
    }
}

$saved = isset($_GET['saved']);
quantlab_admin_start(($post ? 'Редактирование' : 'Новая статья') . ' — админка AM QuantLab');
$slugJs = <<<'JS'
(function () {
  var map = {а:"a",б:"b",в:"v",г:"g",д:"d",е:"e",ё:"e",ж:"zh",з:"z",и:"i",й:"j",к:"k",л:"l",м:"m",н:"n",о:"o",п:"p",р:"r",с:"s",т:"t",у:"u",ф:"f",х:"h",ц:"c",ч:"ch",ш:"sh",щ:"sch",ъ:"",ы:"y",ь:"",э:"e",ю:"yu",я:"ya"};
  function slugify(text) {
    text = String(text || "").toLowerCase();
    text = text.replace(/[а-яё]/g, function (ch) { return map[ch] || ""; });
    return text.replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "statya";
  }
  var title = document.getElementById("title");
  var slug = document.getElementById("slug");
  var preview = document.getElementById("slug-preview");
  if (!title || !slug) return;
  var locked = slug.dataset.locked === "1";
  function sync() {
    if (!locked) slug.value = slugify(title.value);
    if (preview) preview.textContent = "/blog/" + (slug.value || "slug") + "/";
  }
  title.addEventListener("input", sync);
  slug.addEventListener("input", function () {
    locked = true;
    slug.dataset.locked = "1";
    sync();
  });
  sync();
})();
JS;
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => $post ? 'Статья' : 'Новая', 'path' => '/admin/edit.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1><?= $post ? 'Редактирование' : 'Новая статья' ?></h1>
            <?= quantlab_admin_storage_note() ?>
          </div>
          <?php if (!empty($post['slug'])): ?>
            <a class="btn btn-ghost" href="<?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?>" target="_blank" rel="noopener">Открыть</a>
          <?php endif; ?>
        </div>
        <?php if ($saved): ?>
          <p class="form-note" style="display:block">Сохранено. Публичный адрес: <a href="<?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?>"><?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?></a></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <form class="glass pad form admin-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <input type="hidden" name="action" value="save" />
          <label>
            Заголовок
            <input id="title" type="text" name="title" required value="<?= quantlab_h($post['title'] ?? '') ?>" />
          </label>
          <label>
            Слаг в URL
            <input id="slug" type="text" name="slug" value="<?= quantlab_h($post['slug'] ?? '') ?>" data-locked="<?= !empty($post['slug']) ? '1' : '0' ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" />
            <span class="field-hint">Ссылка сразу: <strong id="slug-preview">/blog/slug/</strong></span>
          </label>
          <label>
            Короткое описание
            <textarea name="excerpt" rows="3"><?= quantlab_h($post['excerpt'] ?? '') ?></textarea>
          </label>
          <label>
            Картинка
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" />
            <span class="field-hint">JPG, PNG, WEBP или GIF, до 5 МБ. Можно заменить — просто загрузите новую.</span>
          </label>
          <?php if (!empty($post['image'])): ?>
            <div class="cover-preview">
              <img src="<?= quantlab_h($post['image']) ?>" alt="" />
              <label class="check-row">
                <input type="checkbox" name="remove_image" value="1" />
                Удалить картинку
              </label>
            </div>
          <?php endif; ?>
          <label>
            Текст (Markdown)
            <textarea name="body" rows="16" placeholder="## Подзаголовок&#10;&#10;Абзац текста. Ссылка: [текст](/blog/drugaya-statya/)"><?= quantlab_h($post['body'] ?? '') ?></textarea>
          </label>
          <label>
            Keywords
            <input type="text" name="keywords" value="<?= quantlab_h($post['keywords'] ?? '') ?>" placeholder="торговые роботы, bybit, api, финтех" />
            <span class="field-hint">Через запятую. Попадут в meta keywords и разметку статьи.</span>
          </label>
          <label>
            SEO-заголовок
            <input type="text" name="seo_title" value="<?= quantlab_h($post['seo_title'] ?? '') ?>" placeholder="Если пусто — заголовок статьи + AM QuantLab" />
          </label>
          <label>
            SEO-описание
            <textarea name="seo_description" rows="2" placeholder="150–160 символов. Это сниппет в Яндексе и Google."><?= quantlab_h($post['seo_description'] ?? '') ?></textarea>
          </label>
          <label class="check-row">
            <input type="checkbox" name="published" value="1" <?= (($post['status'] ?? '') === 'published') ? 'checked' : '' ?> />
            Опубликовать — в sitemap, RSS и пинг Яндексу/Google
          </label>
          <div class="hero-actions">
            <button class="btn" type="submit">Сохранить</button>
            <a class="btn btn-ghost" href="/admin/">К списку</a>
          </div>
        </form>

        <?php if (!empty($post['slug'])): ?>
          <form class="admin-delete" method="post" onsubmit="return confirm('Удалить статью безвозвратно?');">
            <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
            <input type="hidden" name="action" value="delete" />
            <button type="submit" class="btn btn-ghost btn-danger">Удалить пост</button>
          </form>
        <?php endif; ?>
<?php
quantlab_admin_end($slugJs);
