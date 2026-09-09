<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$currentSlug = trim((string) ($_GET['slug'] ?? ''));
$row = $currentSlug !== '' ? quantlab_strategy_load($currentSlug) : null;
if ($currentSlug !== '' && !$row) {
    http_response_code(404);
    echo 'Стратегия не найдена';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    try {
        $saved = quantlab_strategy_save($_POST, $row['slug'] ?? null);
        header('Location: /admin/strategy-edit.php?slug=' . rawurlencode($saved['slug']) . '&saved=1', true, 302);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $row = array_merge($row ?: [], $_POST);
        $row['status'] = !empty($_POST['visible']) ? 'visible' : 'hidden';
        $row['is_test'] = !empty($_POST['is_test']) ? 1 : 0;
        $row['venue'] = (string) ($_POST['venue'] ?? 'comon');
    }
}

$saved = isset($_GET['saved']);
$venues = quantlab_strategy_venues();
quantlab_admin_start(($row ? 'Стратегия' : 'Новая стратегия') . ' — админка AM QuantLab');
$venueJs = <<<'JS'
(function () {
  var venue = document.getElementById("venue");
  var comon = document.getElementById("comon-fields");
  if (!venue || !comon) return;
  function sync() {
    comon.hidden = venue.value !== "comon";
  }
  venue.addEventListener("change", sync);
  sync();
})();
JS;
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Стратегии', 'path' => '/admin/strategies.php'],
            ['name' => $row ? 'Правка' : 'Новая', 'path' => '/admin/strategy-edit.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1><?= $row ? 'Редактирование стратегии' : 'Новая стратегия' ?></h1>
            <?= quantlab_admin_storage_note() ?>
          </div>
          <a class="btn btn-ghost" href="/admin/strategies.php">К списку</a>
        </div>
        <?php if ($saved): ?>
          <p class="form-note" style="display:block">Сохранено. На сайте появится, если включено «Показывать на главной».</p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <form class="glass pad form admin-form" method="post">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <label>
            Площадка
            <select id="venue" name="venue">
              <?php foreach ($venues as $key => $label): ?>
                <option value="<?= quantlab_h($key) ?>" <?= (($row['venue'] ?? 'comon') === $key) ? 'selected' : '' ?>><?= quantlab_h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-hint">Пока Comon и Bybit. У Bybit цифры с API-ключей в .env.</span>
          </label>
          <label>
            Название на слайде
            <input type="text" name="title" required value="<?= quantlab_h($row['title'] ?? '') ?>" />
          </label>
          <label>
            Подпись в точках слайдера
            <input type="text" name="dot" value="<?= quantlab_h($row['dot'] ?? '') ?>" placeholder="Юань · Comon" />
          </label>
          <label>
            Слаг
            <input type="text" name="slug" value="<?= quantlab_h($row['slug'] ?? '') ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="comon-131208" />
          </label>
          <label>
            Надзаголовок
            <input type="text" name="eyebrow" value="<?= quantlab_h($row['eyebrow'] ?? '') ?>" />
          </label>
          <div id="comon-fields">
            <label>
              ID стратегии Comon
              <input type="text" name="comon_id" value="<?= quantlab_h($row['comon_id'] ?? '') ?>" placeholder="131208" />
              <span class="field-hint">Число из адреса comon.ru/strategies/131208/</span>
            </label>
          </div>
          <label>
            Инструмент
            <input type="text" name="instrument" value="<?= quantlab_h($row['instrument'] ?? '') ?>" placeholder="CNYRUB или BTCUSDT" />
          </label>
          <label>
            Ссылка на источник
            <input type="url" name="source_url" value="<?= quantlab_h($row['source_url'] ?? '') ?>" />
          </label>
          <label>
            Короткое описание логики
            <textarea name="lead" rows="3"><?= quantlab_h($row['lead'] ?? '') ?></textarea>
          </label>
          <label>
            Вход
            <input type="text" name="entry" value="<?= quantlab_h($row['entry'] ?? '') ?>" placeholder="2%" />
          </label>
          <label>
            Стоп
            <input type="text" name="stop" value="<?= quantlab_h($row['stop'] ?? '') ?>" placeholder="−5%" />
          </label>
          <label>
            Цель
            <input type="text" name="target" value="<?= quantlab_h($row['target'] ?? '') ?>" placeholder="+15%" />
          </label>
          <label>
            Пункты под логикой (каждый с новой строки)
            <textarea name="notes" rows="5"><?= quantlab_h($row['notes'] ?? '') ?></textarea>
          </label>
          <label>
            Порядок
            <input type="number" name="sort_order" value="<?= quantlab_h((string) ($row['sort_order'] ?? 10)) ?>" />
          </label>
          <label class="check-row">
            <input type="checkbox" name="visible" value="1" <?= (($row['status'] ?? 'visible') === 'visible') ? 'checked' : '' ?> />
            Показывать на главной
          </label>
          <label class="check-row">
            <input type="checkbox" name="is_test" value="1" <?= !empty($row['is_test']) ? 'checked' : '' ?> />
            Пометить как тест
          </label>
          <div class="hero-actions">
            <button class="btn" type="submit">Сохранить</button>
            <a class="btn btn-ghost" href="/admin/strategies.php">Отмена</a>
          </div>
        </form>
<?php
quantlab_admin_end($venueJs);
