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
    if ($action === 'telegram') {
        $slug = (string) ($post['slug'] ?? '');
        $sent = function_exists('quantlab_telegram_send_article')
            ? quantlab_telegram_send_article($slug)
            : ['ok' => false, 'error' => 'Telegram недоступен'];
        $qs = !empty($sent['ok']) ? 'tg=1' : 'tg=err';
        header('Location: /admin/edit.php?slug=' . rawurlencode($slug) . '&' . $qs, true, 302);
        exit;
    }
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
        $title = (string) ($_POST['title'] ?? '');
        $slug = (string) ($_POST['slug'] ?? '');
        $excerpt = (string) ($_POST['excerpt'] ?? '');
        $body = (string) ($_POST['body'] ?? '');
        $keywords = (string) ($_POST['keywords'] ?? '');
        $seo = (string) ($_POST['seo_description'] ?? '');
        $paste = (string) ($_POST['paste'] ?? '');
        $source = trim($paste) !== '' ? $paste : $body;
        if (quantlab_blog_looks_like_paste($source)) {
            $parsed = quantlab_blog_parse_paste($source);
            if ($parsed['title'] !== '') {
                $title = $parsed['title'];
            }
            if ($parsed['slug'] !== '') {
                $slug = $parsed['slug'];
            }
            if ($parsed['excerpt'] !== '') {
                $excerpt = $parsed['excerpt'];
            }
            if ($parsed['keywords'] !== '') {
                $keywords = $parsed['keywords'];
            }
            if ($parsed['seo_description'] !== '') {
                $seo = $parsed['seo_description'];
            }
            if ($parsed['body'] !== '') {
                $body = $parsed['body'];
            }
        }
        $saved = quantlab_blog_save([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'body' => $body,
            'image' => $image,
            'keywords' => $keywords,
            'seo_description' => $seo,
            'status' => !empty($_POST['published']) ? 'published' : 'draft',
            'telegram_notify' => !empty($_POST['telegram_notify']),
        ], $post['slug'] ?? null);
        $qs = 'saved=1';
        $tg = $saved['_telegram'] ?? null;
        if (is_array($tg) && !empty($tg['ok'])) {
            $qs .= '&tg=1';
        } elseif (is_array($tg) && (($tg['error'] ?? '') !== 'disabled') && (($tg['error'] ?? '') !== '')) {
            $qs .= '&tg=err';
        }
        header('Location: /admin/edit.php?slug=' . rawurlencode($saved['slug']) . '&' . $qs, true, 302);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $post = array_merge($post ?: [], $_POST);
        $post['status'] = !empty($_POST['published']) ? 'published' : 'draft';
    }
}

$saved = isset($_GET['saved']);
$tgOk = (string) ($_GET['tg'] ?? '') === '1';
$tgErr = (string) ($_GET['tg'] ?? '') === 'err';
$tgReady = function_exists('quantlab_telegram_enabled') && quantlab_telegram_enabled();
$tgSent = !empty($post['slug']) && function_exists('quantlab_telegram_post_sent') && quantlab_telegram_post_sent((string) $post['slug']);
$tgChecked = $tgReady && !$tgSent;
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

  var labels = {
    title: "title",
    "заголовок": "title",
    description: "seo",
    "описание": "seo",
    keywords: "keywords",
    "ключевые слова": "keywords",
    slug: "slug",
    "слаг": "slug"
  };
  function parsePaste(raw) {
    raw = String(raw || "").replace(/\r\n/g, "\n").replace(/\r/g, "\n").trim();
    var lines = raw.split("\n");
    var out = { title: "", slug: "", excerpt: "", seo: "", keywords: "", body: raw };
    var i = 0;
    var consumed = 0;
    var re = /^(?:\*\*)?(Title|Description|Keywords|Slug|Заголовок|Описание|Ключевые слова|Слаг):\s*(?:\*\*)?\s*(.*)$/i;
    while (i < lines.length) {
      var line = lines[i].trim();
      if (line === "") { i++; consumed = i; continue; }
      if (/^-{3,}$/.test(line)) { i++; consumed = i; break; }
      var m = line.match(re);
      if (!m) break;
      var field = labels[m[1].toLowerCase()];
      var value = String(m[2] || "").trim().replace(/^[`*]+|[`*]+$/g, "");
      if (field && value) {
        if (field === "seo") { out.seo = value; out.excerpt = value; }
        else out[field] = value;
      }
      i++;
      consumed = i;
    }
    var body = lines.slice(consumed).join("\n").trim();
    if (out.title && body.indexOf("# ") === 0) {
      var first = body.split("\n")[0].replace(/^#\s+/, "").trim();
      if (first === out.title) {
        body = body.replace(/^#\s+.+\n*/, "").trim();
      }
    }
    if (body) out.body = body;
    return out;
  }
  function looksLike(raw) {
    return /^\s{0,3}(?:\*\*)?(Title|Description|Keywords|Slug|Заголовок|Описание|Ключевые слова|Слаг)\s*\*\*\s*:/im.test(String(raw || "").trim())
      || /^\s{0,3}(?:\*\*)?(Title|Description|Keywords|Slug|Заголовок)\s*:/im.test(String(raw || "").trim());
  }
  function applyPaste() {
    var box = document.getElementById("article-paste");
    if (!box || !box.value.trim()) return;
    var p = parsePaste(box.value);
    if (p.title && title) title.value = p.title;
    if (p.slug && slug) {
      slug.value = p.slug;
      locked = true;
      slug.dataset.locked = "1";
    }
    var excerpt = document.getElementById("excerpt");
    var seo = document.getElementById("seo_description");
    var keywords = document.getElementById("keywords");
    var body = document.getElementById("body");
    if (p.excerpt && excerpt) excerpt.value = p.excerpt;
    if (p.seo && seo) seo.value = p.seo;
    if (p.keywords && keywords) keywords.value = p.keywords;
    if (p.body && body) body.value = p.body;
    box.value = "";
    sync();
  }
  var pasteBtn = document.getElementById("article-parse");
  var pasteBox = document.getElementById("article-paste");
  if (pasteBtn) pasteBtn.addEventListener("click", applyPaste);
  if (pasteBox) {
    pasteBox.addEventListener("paste", function () {
      setTimeout(function () {
        if (looksLike(pasteBox.value)) applyPaste();
      }, 0);
    });
  }
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
            <?= quantlab_admin_telegram_note() ?>
          </div>
          <?php if (!empty($post['slug'])): ?>
            <a class="btn btn-ghost" href="<?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?>" target="_blank" rel="noopener">Открыть</a>
          <?php endif; ?>
        </div>
        <?php if ($saved): ?>
          <p class="form-note" style="display:block">Сохранено. Публичный адрес: <a href="<?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?>"><?= quantlab_h(quantlab_public_path('blog/' . $post['slug'])) ?></a></p>
        <?php endif; ?>
        <?php if ($tgOk): ?>
          <p class="form-note form-note-ok" style="display:block">Отправлено в Telegram: обложка, короткий текст и кнопка «Читать на AM QuantLab».</p>
        <?php elseif ($tgErr): ?>
          <?php
            $tgFail = function_exists('quantlab_telegram_status') ? quantlab_telegram_status() : [];
            $tgFailText = trim((string) ($tgFail['error'] ?? ''));
          ?>
          <p class="form-note form-note-err" style="display:block">В Telegram не ушло<?= $tgFailText !== '' ? ': ' . quantlab_h($tgFailText) : '. Проверьте токен бота и что бот — админ канала.' ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <form class="glass pad form admin-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <input type="hidden" name="action" value="save" />
          <label class="admin-paste">
            Вставить статью целиком
            <textarea id="article-paste" name="paste" rows="8" placeholder="**Title:** ...&#10;**Description:** ...&#10;**Keywords:** ...&#10;**Slug:** slug-statyi&#10;&#10;---&#10;&#10;Текст со ссылками: [статья](/blog/slug/) и https://amquantlab.ru/"></textarea>
            <span class="field-hint">Вставьте текст как из чата: Title, Description, Keywords, Slug и markdown. Поля заполнятся сами, ссылки `[текст](/blog/slug/)` останутся ссылками.</span>
          </label>
          <p class="hero-actions" style="margin:0">
            <button class="btn btn-ghost" type="button" id="article-parse">Разложить по полям</button>
          </p>
          <label>
            Заголовок
            <input id="title" type="text" name="title" value="<?= quantlab_h($post['title'] ?? '') ?>" />
          </label>
          <label>
            Слаг в URL
            <input id="slug" type="text" name="slug" value="<?= quantlab_h($post['slug'] ?? '') ?>" data-locked="<?= !empty($post['slug']) ? '1' : '0' ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" />
            <span class="field-hint">Ссылка сразу: <strong id="slug-preview">/blog/slug/</strong></span>
          </label>
          <label>
            Короткое описание
            <textarea id="excerpt" name="excerpt" rows="3"><?= quantlab_h($post['excerpt'] ?? '') ?></textarea>
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
            <textarea id="body" name="body" rows="22" placeholder="## Подзаголовок&#10;&#10;Абзац. Ссылка: [текст](/blog/drugaya-statya/) или https://amquantlab.ru/"><?= quantlab_h($post['body'] ?? '') ?></textarea>
            <span class="field-hint">Можно вставлять markdown: заголовки, списки, таблицы, [ссылки](/blog/slug/) и обычные URL.</span>
          </label>
          <label>
            Keywords
            <input id="keywords" type="text" name="keywords" value="<?= quantlab_h($post['keywords'] ?? '') ?>" placeholder="торговые роботы, bybit, api, финтех" />
            <span class="field-hint">Через запятую. Попадут в meta keywords и разметку статьи.</span>
          </label>
          <label>
            SEO-описание
            <textarea id="seo_description" name="seo_description" rows="2" placeholder="150–160 символов. Это сниппет в Яндексе и Google."><?= quantlab_h($post['seo_description'] ?? '') ?></textarea>
          </label>
          <label class="check-row">
            <input type="checkbox" name="published" value="1" <?= (($post['status'] ?? '') === 'published') ? 'checked' : '' ?> />
            Опубликовать — в sitemap, RSS и пинг Яндексу/Google
          </label>
          <label class="check-row">
            <input type="checkbox" name="telegram_notify" value="1" <?= $tgChecked ? 'checked' : '' ?> <?= $tgReady ? '' : 'disabled' ?> />
            Вместе с сохранением отправить в Telegram
          </label>
          <?php if (!$tgReady): ?>
            <span class="field-hint">Сначала TELEGRAM_BOT_TOKEN и канал в .env, бот — админ канала.</span>
          <?php elseif ($tgSent): ?>
            <span class="field-hint">Уже уходило в канал. Кнопка ниже отправит ещё раз.</span>
          <?php else: ?>
            <span class="field-hint">Или нажмите «В Telegram» у опубликованной статьи — не обязательно сохранять заново.</span>
          <?php endif; ?>
          <div class="hero-actions">
            <button class="btn" type="submit">Сохранить</button>
            <a class="btn btn-ghost" href="/admin/">К списку</a>
          </div>
        </form>

        <?php if (!empty($post['slug']) && (($post['status'] ?? '') === 'published')): ?>
          <form class="admin-telegram" method="post">
            <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
            <input type="hidden" name="action" value="telegram" />
            <button class="btn" type="submit" <?= $tgReady ? '' : 'disabled' ?>>
              <?= $tgSent ? 'Ещё раз в Telegram' : 'Отправить в Telegram' ?>
            </button>
            <span class="field-hint">Уйдёт обложка, короткий текст без цен и кнопка на статью.</span>
          </form>
        <?php endif; ?>

        <?php if (!empty($post['slug'])): ?>
          <form class="admin-delete" method="post" onsubmit="return confirm('Удалить статью безвозвратно?');">
            <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
            <input type="hidden" name="action" value="delete" />
            <button type="submit" class="btn btn-ghost btn-danger">Удалить пост</button>
          </form>
        <?php endif; ?>
<?php
quantlab_admin_end($slugJs);
