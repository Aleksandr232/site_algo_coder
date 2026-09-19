<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$id = function_exists('quantlab_metrika_id') ? quantlab_metrika_id() : '';
$goals = function_exists('quantlab_metrika_goals') ? quantlab_metrika_goals() : [];

quantlab_admin_start('Цели Метрики — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Метрика', 'path' => '/admin/metrika.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Цели Яндекс Метрики</h1>
            <p class="lead">Счётчик <?= $id !== '' ? quantlab_h($id) : 'не задан' ?>. JavaScript-события для Директа и отчётов.</p>
          </div>
          <?php if ($id !== ''): ?>
            <a class="btn btn-ghost" href="https://metrika.yandex.ru/goals?id=<?= quantlab_h($id) ?>" target="_blank" rel="noopener">Кабинет Метрики</a>
          <?php endif; ?>
        </div>

        <?php if ($id === ''): ?>
          <p class="form-note" style="display:block">В .env нет YANDEX_METRIKA_ID.</p>
        <?php else: ?>
          <div class="glass pad" style="margin-bottom:20px">
            <p class="eyebrow">Формат в кабинете</p>
            <h2>Как создать цель</h2>
            <p>Цели → Добавить цель. Поля одни и те же для всех пяти событий:</p>
            <ul class="field-hint" style="display:block;margin:12px 0 0;padding-left:18px">
              <li><strong>Тип цели:</strong> JavaScript-событие</li>
              <li><strong>Тип условия:</strong> совпадает</li>
              <li><strong>Идентификатор:</strong> латиница, цифры и <code>_</code>, без пробелов, как в таблице — <code>lead</code>, не «Заявка»</li>
              <li><strong>Событие на сайте:</strong> <code>ym(<?= quantlab_h($id) ?>, 'reachGoal', 'lead')</code></li>
              <li><strong>Ценность:</strong> для <code>lead</code> — 20000, для <code>robot_order</code> — 25000, остальным 0. Валюта RUB</li>
            </ul>
          </div>
          <div class="admin-table glass">
            <table>
              <thead>
                <tr>
                  <th>Название</th>
                  <th>Тип</th>
                  <th>Условие</th>
                  <th>Идентификатор</th>
                  <th>Событие</th>
                  <th>Когда</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($goals as $goal): ?>
                  <?php
                    $gid = (string) ($goal['id'] ?? '');
                    $event = "ym(" . $id . ", 'reachGoal', '" . $gid . "')";
                  ?>
                  <tr>
                    <td><?= quantlab_h((string) ($goal['name'] ?? '')) ?></td>
                    <td>JavaScript-событие</td>
                    <td>совпадает</td>
                    <td><code><?= quantlab_h($gid) ?></code></td>
                    <td><code><?= quantlab_h($event) ?></code></td>
                    <td><?= quantlab_h((string) ($goal['when'] ?? '')) ?></td>
                    <td>
                      <button class="btn btn-ghost btn-sm js-metrika-one" type="button" data-goal="<?= quantlab_h($gid) ?>">Тест</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="form-note" style="display:block" id="metrika-note">
            Идентификатор копируй один в один. Если написать «Lead» или «заявка», цель не сработает.
            Либо нажми «Зарегистрировать цели» — Метрика увидит события и предложит их в списке.
          </p>
          <p class="hero-actions">
            <button class="btn" type="button" id="metrika-register">Зарегистрировать цели</button>
          </p>
          <?php if (function_exists('quantlab_render_metrika')) quantlab_render_metrika(); ?>
          <script>
          (function () {
            var note = document.getElementById("metrika-note");
            function fire(name) {
              if (typeof window.quantlabMetrikaGoal !== "function") {
                if (note) note.textContent = "Счётчик ещё не загрузился. Обновите страницу.";
                return false;
              }
              window.quantlabMetrikaGoal(name);
              return true;
            }
            var all = document.getElementById("metrika-register");
            if (all) {
              all.addEventListener("click", function () {
                var goals = window.QUANTLAB_METRIKA_GOALS || [];
                var ok = goals.every(function (name) { return fire(name); });
                if (ok && note) {
                  note.textContent = "Отправлено: " + goals.join(", ") + ". Через минуту открой кабинет → Цели и подтверди предложенные.";
                }
              });
            }
            document.querySelectorAll(".js-metrika-one").forEach(function (btn) {
              btn.addEventListener("click", function () {
                var name = btn.getAttribute("data-goal") || "";
                if (fire(name) && note) note.textContent = "Отправлено: " + name;
              });
            });
          })();
          </script>
        <?php endif; ?>
<?php
quantlab_admin_end();
