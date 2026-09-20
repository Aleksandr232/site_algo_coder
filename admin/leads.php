<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$error = '';
$openLead = [
    'id' => 0,
    'email' => '',
    'name' => '',
    'subject' => '',
    'body' => '',
    'task' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'reply') {
    quantlab_csrf_check();
    $id = (int) ($_POST['lead_id'] ?? 0);
    $lead = quantlab_lead_by_id($id);
    $openLead['id'] = $id;
    $openLead['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $openLead['body'] = (string) ($_POST['body'] ?? '');
    if (!$lead) {
        $error = 'Заявка не найдена';
    } elseif (!quantlab_mail_enabled()) {
        $error = 'Сначала SMTP в .env — письмо должно уйти с info@amquantlab.ru';
    } else {
        $openLead['email'] = quantlab_lead_email($lead);
        $openLead['name'] = (string) ($lead['name'] ?? '');
        $openLead['task'] = (string) ($lead['message'] ?? '');
        try {
            $sent = quantlab_lead_reply_mail(
                $lead,
                $openLead['body'],
                $openLead['subject']
            );
            header('Location: /admin/leads.php?sent=1&to=' . rawurlencode((string) $sent['to']), true, 302);
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$leads = quantlab_leads_all();
$sentOk = (string) ($_GET['sent'] ?? '') === '1';
$sentTo = trim((string) ($_GET['to'] ?? ''));
$fromBox = function_exists('quantlab_env') ? quantlab_env('SMTP_FROM', 'info@amquantlab.ru') : 'info@amquantlab.ru';

quantlab_admin_start('Заявки — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Заявки', 'path' => '/admin/leads.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Заявки с формы</h1>
            <p class="lead">Клиенты с главной страницы. <?= count($leads) ?> шт. Ответ уходит с <?= quantlab_h($fromBox) ?>.</p>
            <?= quantlab_admin_storage_note() ?>
            <?= quantlab_admin_mail_note() ?>
          </div>
          <a class="btn btn-ghost" href="/admin/">К статьям</a>
        </div>

        <?php if ($sentOk): ?>
          <p class="form-note" style="display:block">Письмо ушло<?= $sentTo !== '' ? ' на ' . quantlab_h($sentTo) : '' ?> с <?= quantlab_h($fromBox) ?>.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <?php if (!$leads): ?>
          <div class="glass pad empty-blog">
            <p>Заявок ещё нет. Когда клиент отправит форму на сайте, она появится здесь.</p>
          </div>
        <?php else: ?>
          <div class="admin-table glass">
            <table>
              <thead>
                <tr>
                  <th>Дата</th>
                  <th>Имя</th>
                  <th>Контакт</th>
                  <th>Рынок / робот</th>
                  <th>Задача</th>
                  <th>Ответ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leads as $lead): ?>
                  <?php
                    $ts = strtotime((string) ($lead['created_at'] ?? ''));
                    $when = $ts ? date('d.m.Y H:i', $ts) : (string) ($lead['created_at'] ?? '');
                    $contact = (string) ($lead['contact'] ?? '');
                    $email = function_exists('quantlab_lead_email') ? quantlab_lead_email($lead) : '';
                    $leadId = (int) ($lead['id'] ?? 0);
                    $last = $leadId > 0 ? quantlab_lead_last_reply($leadId) : [];
                    $lastAt = '';
                    if (!empty($last['at'])) {
                        $lastTs = strtotime((string) $last['at']);
                        $lastAt = $lastTs ? date('d.m.Y H:i', $lastTs) : (string) $last['at'];
                    }
                    $name = trim((string) ($lead['name'] ?? ''));
                    $payload = [
                        'id' => $leadId,
                        'email' => $email,
                        'name' => $name,
                        'subject' => !empty($lead['robot_title'])
                            ? ('AM QuantLab: по заявке «' . $lead['robot_title'] . '»')
                            : 'AM QuantLab: по вашей заявке',
                        'body' => ($name !== '' ? ('Здравствуйте, ' . $name . '.') : 'Здравствуйте.') . "\n\n",
                        'task' => (string) ($lead['message'] ?? ''),
                    ];
                  ?>
                  <tr>
                    <td><?= quantlab_h($when) ?></td>
                    <td><?= quantlab_h((string) ($lead['name'] ?? '')) ?></td>
                    <td>
                      <?php if ($email !== ''): ?>
                        <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a>
                        <?php if ($contact !== '' && strcasecmp($contact, $email) !== 0): ?>
                          <br /><span class="field-hint"><?= quantlab_h($contact) ?></span>
                        <?php endif; ?>
                      <?php else: ?>
                        <?= quantlab_h($contact) ?>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= quantlab_h(quantlab_lead_market_label((string) ($lead['market'] ?? ''))) ?>
                      <?php if (!empty($lead['robot_title'])): ?>
                        <br /><strong><?= quantlab_h((string) $lead['robot_title']) ?></strong>
                        <?php if (!empty($lead['robot_price'])): ?>
                          <br /><span class="field-hint"><?= quantlab_h((string) $lead['robot_price']) ?></span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                    <td class="lead-msg"><?= quantlab_h((string) ($lead['message'] ?? '')) ?></td>
                    <td class="lead-reply">
                      <?php if ($email === ''): ?>
                        <span class="field-hint">Нет почты</span>
                      <?php else: ?>
                        <button
                          class="btn btn-sm js-lead-reply"
                          type="button"
                          data-lead="<?= quantlab_h((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"
                        >Ответить</button>
                        <?php if ($lastAt !== ''): ?>
                          <br /><span class="field-hint">Писали <?= quantlab_h($lastAt) ?></span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="modal" id="lead-reply-modal" hidden>
            <div class="modal-backdrop" data-lead-close></div>
            <div class="modal-card glass pad" role="dialog" aria-modal="true" aria-labelledby="lead-reply-title">
              <button class="modal-close" type="button" data-lead-close aria-label="Закрыть">×</button>
              <p class="eyebrow">Ответ клиенту</p>
              <h2 id="lead-reply-title">Написать на почту</h2>
              <p class="modal-date" id="lead-reply-meta"></p>
              <p class="field-hint" id="lead-reply-task"></p>
              <form class="admin-form lead-reply-modal-form" method="post">
                <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                <input type="hidden" name="action" value="reply" />
                <input type="hidden" name="lead_id" id="lead-reply-id" value="" />
                <label>
                  Тема
                  <input id="lead-reply-subject" type="text" name="subject" required />
                </label>
                <label>
                  Письмо
                  <textarea id="lead-reply-body" name="body" rows="8" required placeholder="Текст письма клиенту"></textarea>
                </label>
                <p class="hero-actions">
                  <button class="btn" type="submit">Отправить с <?= quantlab_h($fromBox) ?></button>
                  <button class="btn btn-ghost" type="button" data-lead-close>Отмена</button>
                </p>
              </form>
            </div>
          </div>
        <?php endif; ?>
<?php
$openJson = json_encode($openLead, JSON_UNESCAPED_UNICODE);
$replyJs = <<<JS
(function () {
  var modal = document.getElementById("lead-reply-modal");
  if (!modal) return;
  var idInput = document.getElementById("lead-reply-id");
  var subjectInput = document.getElementById("lead-reply-subject");
  var bodyInput = document.getElementById("lead-reply-body");
  var meta = document.getElementById("lead-reply-meta");
  var task = document.getElementById("lead-reply-task");
  function fill(data) {
    if (idInput) idInput.value = data.id || "";
    if (subjectInput) subjectInput.value = data.subject || "";
    if (bodyInput) bodyInput.value = data.body || "";
    if (meta) {
      var bits = [];
      if (data.name) bits.push(data.name);
      if (data.email) bits.push(data.email);
      meta.textContent = bits.join(" · ");
    }
    if (task) task.textContent = data.task ? ("Заявка: " + data.task) : "";
  }
  function open(data) {
    fill(data || {});
    modal.hidden = false;
    document.body.classList.add("modal-open");
    if (bodyInput) {
      bodyInput.focus();
      var len = bodyInput.value.length;
      bodyInput.setSelectionRange(len, len);
    }
  }
  function close() {
    modal.hidden = true;
    document.body.classList.remove("modal-open");
  }
  document.addEventListener("click", function (event) {
    var btn = event.target.closest(".js-lead-reply");
    if (btn) {
      var raw = btn.getAttribute("data-lead") || "{}";
      var data = {};
      try { data = JSON.parse(raw); } catch (e) { data = {}; }
      open(data);
      return;
    }
    if (event.target.closest("[data-lead-close]")) close();
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !modal.hidden) close();
  });
  var reopen = {$openJson};
  if (reopen && Number(reopen.id) > 0) open(reopen);
})();
JS;
quantlab_admin_end($replyJs);
