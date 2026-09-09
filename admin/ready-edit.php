<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$currentSlug = trim((string) ($_GET['slug'] ?? ''));
$row = $currentSlug !== '' ? quantlab_ready_load($currentSlug) : null;
if ($currentSlug !== '' && !$row) {
    http_response_code(404);
    echo 'Робот не найден';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    try {
        $image = quantlab_ready_handle_image(
            $row['image'] ?? null,
            $_FILES['image'] ?? [],
            !empty($_POST['remove_image'])
        );
        $saved = quantlab_ready_save([
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'description' => $_POST['description'] ?? '',
            'price' => $_POST['price'] ?? '',
            'venue' => $_POST['venue'] ?? 'finam',
            'image' => $image,
            'keywords' => $_POST['keywords'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_description' => $_POST['seo_description'] ?? '',
            'sort_order' => $_POST['sort_order'] ?? 10,
            'visible' => !empty($_POST['visible']),
        ], $row['slug'] ?? null);
        header('Location: /admin/ready-edit.php?slug=' . rawurlencode($saved['slug']) . '&saved=1', true, 302);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $row = array_merge($row ?: [], $_POST);
        $row['status'] = !empty($_POST['visible']) ? 'visible' : 'hidden';
        $row['venue'] = (string) ($_POST['venue'] ?? 'finam');
    }
}

$saved = isset($_GET['saved']);
$venues = quantlab_ready_venues();
quantlab_admin_start(($row ? 'Робот' : 'Новый робот') . ' — админка AM QuantLab');
$slugJs = <<<'JS'
(function () {
  var map = {а:"a",б:"b",в:"v",г:"g",д:"d",е:"e",ё:"e",ж:"zh",з:"z",и:"i",й:"j",к:"k",л:"l",м:"m",н:"n",о:"o",п:"p",р:"r",с:"s",т:"t",у:"u",ф:"f",х:"h",ц:"c",ч:"ch",ш:"sh",щ:"sch",ъ:"",ы:"y",ь:"",э:"e",ю:"yu",я:"ya"};
  function slugify(text) {
    text = String(text || "").toLowerCase();
    text = text.replace(/[а-яё]/g, function (ch) { return map[ch] || ""; });
    return text.replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "robot";
  }
  var title = document.getElementById("title");
  var slug = document.getElementById("slug");
  var preview = document.getElementById("slug-preview");
  if (!title || !slug) return;
  var locked = slug.dataset.locked === "1";
  function sync() {
    if (!locked) slug.value = slugify(title.value);
    if (preview) preview.textContent = "/robots/" + (slug.value || "slug") + "/";
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
            ['name' => 'Роботы', 'path' => '/admin/ready.php'],
            ['name' => $row ? 'Правка' : 'Новый', 'path' => '/admin/ready-edit.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1><?= $row ? 'Редактирование робота' : 'Новый робот' ?></h1>
            <?= quantlab_admin_storage_note() ?>
          </div>
          <a class="btn btn-ghost" href="/admin/ready.php">К списку</a>
          <?php if (!empty($row['slug'])): ?>
            <a class="btn btn-ghost" href="<?= quantlab_h(quantlab_ready_url($row['slug'])) ?>" target="_blank" rel="noopener">Открыть</a>
          <?php endif; ?>
        </div>
        <?php if ($saved): ?>
          <p class="form-note" style="display:block">Сохранено. Страница: <a href="<?= quantlab_h(quantlab_ready_url($row['slug'])) ?>"><?= quantlab_h(quantlab_ready_url($row['slug'])) ?></a></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <form class="glass pad form admin-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <label>
            Название
            <input id="title" type="text" name="title" required value="<?= quantlab_h($row['title'] ?? '') ?>" placeholder="Юань тренд 2-5-15" />
          </label>
          <label>
            Слаг
            <input id="slug" type="text" name="slug" value="<?= quantlab_h($row['slug'] ?? '') ?>" data-locked="<?= !empty($row['slug']) ? '1' : '0' ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" />
            <span class="field-hint">Адрес: <strong id="slug-preview">/robots/slug/</strong></span>
          </label>
          <label>
            Площадка
            <select name="venue">
              <?php foreach ($venues as $key => $label): ?>
                <option value="<?= quantlab_h($key) ?>" <?= (($row['venue'] ?? 'finam') === $key) ? 'selected' : '' ?>><?= quantlab_h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Цена
            <input type="text" name="price" required value="<?= quantlab_h($row['price'] ?? '') ?>" placeholder="45 000 ₽" />
            <span class="field-hint">Как на витрине, например 45 000 ₽ или от 20 000 ₽.</span>
          </label>
          <label>
            Описание
            <textarea name="description" rows="8" required placeholder="Что делает робот, инструмент, риск. Абзацы с пустой строки — на странице будут отдельными."><?= quantlab_h($row['description'] ?? '') ?></textarea>
          </label>
          <label>
            Title для поиска
            <input type="text" name="seo_title" value="<?= quantlab_h($row['seo_title'] ?? '') ?>" placeholder="Юань тренд — готовый робот для Мосбиржи | AM QuantLab" />
            <span class="field-hint">Если пусто, соберём из названия. Это title в Google и Яндексе.</span>
          </label>
          <label>
            SEO-описание
            <textarea name="seo_description" rows="2" placeholder="150–160 символов для сниппета"><?= quantlab_h($row['seo_description'] ?? '') ?></textarea>
          </label>
          <label>
            Keywords
            <input type="text" name="keywords" value="<?= quantlab_h($row['keywords'] ?? '') ?>" placeholder="торговый робот, юань, мосбиржа, финам" />
          </label>
          <label>
            Картинка робота
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" />
            <span class="field-hint">JPG, PNG, WEBP или GIF, до 5 МБ. Показывается на карточке.</span>
          </label>
          <?php if (!empty($row['image'])): ?>
            <div class="cover-preview">
              <img src="<?= quantlab_h($row['image']) ?>" alt="" />
              <label class="check-row">
                <input type="checkbox" name="remove_image" value="1" />
                Удалить картинку
              </label>
            </div>
          <?php endif; ?>
          <label>
            Порядок
            <input type="number" name="sort_order" value="<?= quantlab_h((string) ($row['sort_order'] ?? 10)) ?>" />
          </label>
          <label class="check-row">
            <input type="checkbox" name="visible" value="1" <?= (($row['status'] ?? 'visible') === 'visible') ? 'checked' : '' ?> />
            Показывать на сайте и в sitemap
          </label>
          <div class="hero-actions">
            <button class="btn" type="submit">Сохранить</button>
            <a class="btn btn-ghost" href="/admin/ready.php">Отмена</a>
          </div>
        </form>
<?php
quantlab_admin_end($slugJs);
