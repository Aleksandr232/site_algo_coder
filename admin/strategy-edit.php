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
if ($row && quantlab_strategy_uses_yield_api((string) ($row['venue'] ?? ''))) {
    $row = quantlab_strategy_ensure_yield_token($row);
}
$venues = quantlab_strategy_venues();
$yieldToken = (string) ($row['yield_token'] ?? '');
$yieldSlug = (string) ($row['slug'] ?? '');
$yieldPost = $yieldToken !== '' && $yieldSlug !== '' ? quantlab_yield_post_url($yieldSlug, $yieldToken) : '';
$yieldGet = $yieldSlug !== '' ? quantlab_yield_get_url($yieldSlug) : '';
quantlab_admin_start(($row ? 'Стратегия' : 'Новая стратегия') . ' — админка AM QuantLab');
$venueJs = <<<'JS'
(function () {
  var venue = document.getElementById("venue");
  var comon = document.getElementById("comon-fields");
  var bybit = document.getElementById("bybit-fields");
  var forex = document.getElementById("forex-fields");
  if (!venue) return;
  function sync() {
    if (comon) comon.hidden = venue.value !== "comon";
    if (bybit) bybit.hidden = venue.value !== "bybit";
    if (forex) forex.hidden = venue.value !== "forex";
  }
  venue.addEventListener("change", sync);
  sync();
  document.querySelectorAll("[data-copy]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var el = document.getElementById(btn.getAttribute("data-copy"));
      if (!el) return;
      var text = el.value || el.textContent || "";
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          btn.textContent = "Скопировано";
          setTimeout(function () { btn.textContent = "Копировать"; }, 1400);
        });
        return;
      }
      if (el.select) el.select();
      document.execCommand("copy");
    });
  });
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
          <p class="form-note" style="display:block">Сохранено. На сайте появится, если включено «Показывать на главной».<?php if (($row['venue'] ?? '') === 'forex'): ?> Ниже — URL и ключ именно этого робота.<?php endif; ?></p>
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
            <span class="field-hint">Comon и Bybit тянут цифры сами. Forex — робот шлёт доходность на свой URL и ключ.</span>
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
            <input type="text" name="instrument" value="<?= quantlab_h($row['instrument'] ?? '') ?>" placeholder="CNYRUB, BTCUSDT или EURUSD" />
          </label>
          <div id="forex-fields" class="admin-api-box">
            <p class="eyebrow">API этого робота</p>
            <?php if ($yieldPost === ''): ?>
              <p>Сохраните стратегию — появится отдельный ключ. Им робот пишет только в этот слайд, а не в общий поток.</p>
            <?php else: ?>
              <p>Вставьте URL и ключ в робота. Токен привязан к <code><?= quantlab_h($yieldSlug) ?></code>: чужой робот с другим ключом сюда не попадёт.</p>
              <label>
                POST — куда слать доходность
                <input id="yield-post-url" type="text" readonly value="<?= quantlab_h($yieldPost) ?>" />
                <span class="field-hint"><button class="linkish" type="button" data-copy="yield-post-url">Копировать</button> · ритм раз в день или каждые N минут по Москве, кнопка «Отправить» — сразу.</span>
              </label>
              <label>
                Ключ этого робота
                <input id="yield-token" type="text" readonly value="<?= quantlab_h($yieldToken) ?>" />
                <span class="field-hint"><button class="linkish" type="button" data-copy="yield-token">Копировать</button> · можно query <code>token=</code>, заголовок <code>X-Yield-Token</code> или <code>Authorization: Bearer</code>.</span>
              </label>
              <label>
                GET — график на сайте, без ключа
                <input id="yield-get-url" type="text" readonly value="<?= quantlab_h($yieldGet) ?>" />
                <span class="field-hint"><button class="linkish" type="button" data-copy="yield-get-url">Копировать</button></span>
              </label>
              <p class="field-hint">Тело JSON. Числа без кавычек. Подстановки робота: <code>{{date}}</code>, <code>{{time}}</code>, <code>{{returnPercent}}</code>, <code>{{equity}}</code>, <code>{{balance}}</code>, <code>{{realizedPnl}}</code>, <code>{{running}}</code>, <code>{{points}}</code>.</p>
              <pre class="admin-api-sample">{
  "returnPercent": {{returnPercent}},
  "equity": {{equity}},
  "balance": {{balance}},
  "realizedPnl": {{realizedPnl}},
  "running": {{running}},
  "points": {{points}}
}</pre>
              <p class="field-hint">Или готовые точки: <code>{"date":"2026-09-23","returnPercent":12.4,"equity":11240,"points":[{"date":"2026-09-01","equity":10000,"returnPercent":0}]}</code></p>
            <?php endif; ?>
          </div>
          <div id="bybit-fields">
            <label>
              Рынок Bybit
              <select name="bybit_market">
                <?php foreach (quantlab_strategy_bybit_markets() as $key => $label): ?>
                  <option value="<?= quantlab_h($key) ?>" <?= (quantlab_strategy_bybit_market($row['bybit_market'] ?? 'linear') === $key) ? 'selected' : '' ?>><?= quantlab_h($label) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field-hint">Спот и фьючерс считаются отдельно. Можно завести два слайда: BTCUSDT спот и BTCUSDT фьючерс.</span>
            </label>
            <label>
              Считать с даты
              <input type="date" name="since_date" value="<?= quantlab_h(($row['since_date'] ?? '') !== '' ? $row['since_date'] : (($row['slug'] ?? '') === 'bybit-btc' ? '2026-08-31' : date('Y-m-d'))) ?>" />
              <span class="field-hint">Доходность и график только после этой даты — когда стратегию добавили.</span>
            </label>
            <label>
              Стартовый баланс, USDT
              <input type="text" name="start_balance" value="<?= quantlab_h($row['start_balance'] ?? '') ?>" placeholder="необязательно" />
              <span class="field-hint">Если пусто, старт берётся по счёту на дату запуска. Для второй стратегии на том же счёте лучше указать депозит.</span>
            </label>
          </div>
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
