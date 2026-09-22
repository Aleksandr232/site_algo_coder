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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'seen') {
        $id = (int) ($_POST['lead_id'] ?? 0);
        if ($id > 0 && function_exists('quantlab_lead_thread_mark_read')) {
            quantlab_lead_thread_mark_read($id);
        }
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: /admin/leads.php', true, 302);
        exit;
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['lead_id'] ?? 0);
        if ($id > 0 && function_exists('quantlab_lead_delete') && quantlab_lead_delete($id)) {
            header('Location: /admin/leads.php?deleted=1', true, 302);
            exit;
        }
        $error = 'Заявку не удалось удалить';
    }
    if ($action === 'sync') {
        if (function_exists('quantlab_inbox_sync')) {
            try {
                quantlab_inbox_sync(true, 0);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        if ($error === '') {
            header('Location: /admin/leads.php?sync=1', true, 302);
            exit;
        }
    }
    if ($action === 'reply') {
        $id = (int) ($_POST['lead_id'] ?? 0);
        $lead = quantlab_lead_by_id($id);
        $openLead['id'] = $id;
        $openLead['subject'] = trim((string) ($_POST['subject'] ?? ''));
        $openLead['body'] = (string) ($_POST['body'] ?? '');
        if ($lead) {
            $openLead['email'] = quantlab_lead_email($lead);
            $openLead['name'] = (string) ($lead['name'] ?? '');
            $openLead['task'] = (string) ($lead['message'] ?? '');
        }
        if (!$lead) {
            $error = 'Заявка не найдена';
        } elseif (!quantlab_mail_enabled()) {
            $error = 'Сначала SMTP в .env — письмо должно уйти с info@amquantlab.ru';
            if (function_exists('quantlab_lead_mark_replied')) {
                quantlab_lead_mark_replied($id, (string) $openLead['email'], $openLead['subject'], false, $error);
            }
        } else {
            try {
                $sent = quantlab_lead_reply_mail(
                    $lead,
                    $openLead['body'],
                    $openLead['subject']
                );
                header('Location: /admin/leads.php?sent=1&to=' . rawurlencode((string) $sent['to']) . '&open=' . $id, true, 302);
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                if (function_exists('quantlab_lead_mark_replied')) {
                    quantlab_lead_mark_replied($id, (string) $openLead['email'], $openLead['subject'], false, $error);
                }
            }
        }
    }
}

$inbox = ['ok' => null, 'imported' => 0, 'error' => ''];
if (function_exists('quantlab_inbox_sync') && quantlab_mail_enabled() && $error === '') {
    try {
        $inbox = quantlab_inbox_sync(false, 0);
    } catch (Throwable $e) {
        $inbox = ['ok' => false, 'error' => $e->getMessage(), 'imported' => 0];
    }
}

$leads = quantlab_leads_all();
$sentOk = (string) ($_GET['sent'] ?? '') === '1';
$sentTo = trim((string) ($_GET['to'] ?? ''));
$synced = (string) ($_GET['sync'] ?? '') === '1';
$deletedOk = (string) ($_GET['deleted'] ?? '') === '1';
$openId = (int) ($_GET['open'] ?? 0);
$fromBox = function_exists('quantlab_env') ? quantlab_env('SMTP_FROM', 'info@amquantlab.ru') : 'info@amquantlab.ru';
$threads = [];
if ($leads) {
    usort($leads, static function ($a, $b) {
        $ida = (int) ($a['id'] ?? 0);
        $idb = (int) ($b['id'] ?? 0);
        $ua = $ida > 0 ? quantlab_lead_thread_unread($ida) : 0;
        $ub = $idb > 0 ? quantlab_lead_thread_unread($idb) : 0;
        if ($ua !== $ub) {
            return $ub <=> $ua;
        }
        return $idb <=> $ida;
    });
}

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
            <p class="lead">Клиенты с главной страницы. <?= count($leads) ?> шт. Ответ уходит с <?= quantlab_h($fromBox) ?>. Если клиент напишет на эту почту — письмо попадёт в диалог.</p>
            <?php if (function_exists('quantlab_cron_inbox_url')): ?>
            <p class="admin-storage">Cron раз в час, GET: <code><?= quantlab_h(quantlab_cron_inbox_url()) ?></code></p>
            <?php endif; ?>
            <?= quantlab_admin_storage_note() ?>
            <?= quantlab_admin_mail_note() ?>
          </div>
          <div class="hero-actions">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
              <input type="hidden" name="action" value="sync" />
              <button class="btn btn-ghost" type="submit">Проверить почту</button>
            </form>
            <a class="btn btn-ghost" href="/admin/">К статьям</a>
          </div>
        </div>

        <?php if ($deletedOk): ?>
          <p class="form-note form-note-ok" style="display:block">Заявка удалена.</p>
        <?php endif; ?>
        <?php if ($sentOk): ?>
          <p class="form-note form-note-ok" style="display:block">Статус: отправлено<?= $sentTo !== '' ? ' на ' . quantlab_h($sentTo) : '' ?> с <?= quantlab_h($fromBox) ?>.</p>
        <?php endif; ?>
        <?php if ($synced && empty($inbox['error'])): ?>
          <p class="form-note form-note-ok" style="display:block">Почта проверена<?= !empty($inbox['imported']) ? ': новых писем ' . (int) $inbox['imported'] : '' ?>.</p>
        <?php endif; ?>
        <?php if (!empty($inbox['error'])): ?>
          <p class="form-note form-note-err" style="display:block">Входящие: <?= quantlab_h((string) $inbox['error']) ?></p>
        <?php elseif (!empty($inbox['imported'])): ?>
          <p class="form-note form-note-ok" style="display:block">Подтянули <?= (int) $inbox['imported'] ?> входящих писем в диалоги.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <p class="form-note form-note-err" style="display:block">Статус: не ушло. <?= quantlab_h($error) ?></p>
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
                  <th></th>
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
                    $thread = $leadId > 0 ? quantlab_lead_thread($leadId, $lead) : [];
                    $unread = $leadId > 0 ? quantlab_lead_thread_unread($leadId) : 0;
                    $inbound = quantlab_lead_inbound_count($thread);
                    $hasIn = $inbound > 0;
                    $status = quantlab_lead_reply_status($last, $unread, $hasIn);
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
                    if ($leadId > 0) {
                        $threads[(string) $leadId] = $thread;
                    }
                    if ($openId > 0 && $openId === $leadId) {
                        $openLead = array_merge($openLead, $payload);
                    }
                    $rowClass = 'js-lead-open';
                    if ($unread > 0) {
                        $rowClass .= ' is-unread';
                    }
                  ?>
                  <tr class="<?= quantlab_h($rowClass) ?>"<?= $email !== '' ? ' data-lead="' . quantlab_h((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
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
                    <td class="lead-msg"><?= quantlab_h(quantlab_text_clip((string) ($lead['message'] ?? ''), 140)) ?></td>
                    <td class="lead-reply">
                      <span class="lead-count <?= $unread > 0 ? 'badge badge-in' : 'badge' ?>" title="Сколько писем клиент прислал нам">
                        <?= quantlab_h(quantlab_ru_count($inbound, 'сообщение', 'сообщения', 'сообщений')) ?>
                      </span>
                      <?php if ($email === ''): ?>
                        <span class="badge">Нет почты</span>
                      <?php else: ?>
                        <span class="<?= quantlab_h($status['class']) ?>"><?= quantlab_h($status['label']) ?></span>
                        <button
                          class="btn btn-sm js-lead-reply"
                          type="button"
                          data-lead="<?= quantlab_h((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"
                        ><?= $unread > 0 || $hasIn ? 'Открыть' : 'Написать' ?></button>
                      <?php endif; ?>
                      <form method="post" class="lead-delete" onsubmit="return confirm('Удалить заявку безвозвратно?');">
                        <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="lead_id" value="<?= (int) $leadId ?>" />
                        <button type="submit" class="linkish">Удалить</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="modal" id="lead-reply-modal" hidden>
            <div class="modal-backdrop" data-lead-close></div>
            <div class="modal-card modal-card-dialog" role="dialog" aria-modal="true" aria-labelledby="lead-reply-title">
              <span class="lead-chat-accent" aria-hidden="true"></span>
              <header class="lead-chat-head">
                <span class="lead-chat-avatar" id="lead-reply-avatar" aria-hidden="true">A</span>
                <div class="lead-chat-who">
                  <h2 id="lead-reply-title">Клиент</h2>
                  <p class="lead-chat-meta" id="lead-reply-meta"></p>
                </div>
                <button class="modal-close" type="button" data-lead-close aria-label="Закрыть">×</button>
              </header>
              <div class="lead-thread" id="lead-thread"></div>
              <form class="form lead-chat-composer" method="post" id="lead-reply-form">
                <p class="form-note form-note-err lead-chat-status" id="lead-reply-status" <?= $error !== '' ? '' : 'hidden' ?>><?= $error !== '' ? quantlab_h('Статус: не ушло. ' . $error) : '' ?></p>
                <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                <input type="hidden" name="action" value="reply" />
                <input type="hidden" name="lead_id" id="lead-reply-id" value="" />
                <input id="lead-reply-to" type="hidden" />
                <input id="lead-reply-subject" type="hidden" name="subject" value="" />
                <div class="lead-chat-compose-row">
                  <label class="lead-chat-input">
                    <span class="visually-hidden">Ответ</span>
                    <textarea id="lead-reply-body" name="body" rows="1" required placeholder="Написать ответ…"></textarea>
                  </label>
                  <button class="btn" type="submit" id="lead-reply-submit">Отправить</button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
<?php
$openJson = json_encode($openLead, JSON_UNESCAPED_UNICODE);
$threadsJson = json_encode($threads, JSON_UNESCAPED_UNICODE);
$csrfJs = json_encode(quantlab_csrf_token(), JSON_UNESCAPED_UNICODE);
$replyJs = <<<JS
(function () {
  var modal = document.getElementById("lead-reply-modal");
  if (!modal) return;
  var idInput = document.getElementById("lead-reply-id");
  var toInput = document.getElementById("lead-reply-to");
  var subjectInput = document.getElementById("lead-reply-subject");
  var bodyInput = document.getElementById("lead-reply-body");
  var title = document.getElementById("lead-reply-title");
  var meta = document.getElementById("lead-reply-meta");
  var avatar = document.getElementById("lead-reply-avatar");
  var threadBox = document.getElementById("lead-thread");
  var status = document.getElementById("lead-reply-status");
  var form = document.getElementById("lead-reply-form");
  var submit = document.getElementById("lead-reply-submit");
  var threads = {$threadsJson} || {};
  var csrf = {$csrfJs};
  function pad(n) { return n < 10 ? "0" + n : "" + n; }
  function parseDate(raw) {
    if (!raw) return null;
    var d = new Date(raw);
    return isNaN(d.getTime()) ? null : d;
  }
  function fmtTime(raw) {
    var d = parseDate(raw);
    if (!d) return raw || "";
    return pad(d.getHours()) + ":" + pad(d.getMinutes());
  }
  function dayKey(raw) {
    var d = parseDate(raw);
    if (!d) return "";
    return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  }
  function dayLabel(raw) {
    var d = parseDate(raw);
    if (!d) return "";
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    var then = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    if (then === today) return "Сегодня";
    if (then === today - 86400000) return "Вчера";
    return pad(d.getDate()) + "." + pad(d.getMonth() + 1) + "." + d.getFullYear();
  }
  function threadRows(id) {
    return threads[String(id)] || [];
  }
  function hasConversation(id) {
    return threadRows(id).some(function (msg) {
      return msg.kind === "reply" || msg.kind === "ack";
    });
  }
  function lastSubject(id, fallback) {
    var rows = threadRows(id);
    for (var i = rows.length - 1; i >= 0; i--) {
      if (rows[i].subject) {
        var subj = String(rows[i].subject);
        return /^re:/i.test(subj) ? subj : "Re: " + subj;
      }
    }
    return fallback || "";
  }
  function renderThread(id) {
    if (!threadBox) return;
    threadBox.innerHTML = "";
    var rows = threadRows(id);
    if (!rows.length) {
      var empty = document.createElement("p");
      empty.className = "field-hint";
      empty.textContent = "Пока только заявка с сайта.";
      threadBox.appendChild(empty);
      return;
    }
    var lastDay = "";
    rows.forEach(function (msg) {
      var day = dayKey(msg.at);
      if (day && day !== lastDay) {
        lastDay = day;
        var sep = document.createElement("div");
        sep.className = "lead-day";
        sep.textContent = dayLabel(msg.at);
        threadBox.appendChild(sep);
      }
      var wrap = document.createElement("div");
      wrap.className = "lead-bubble " + (msg.dir === "out" ? "lead-bubble-out" : "lead-bubble-in");
      if (msg.dir === "in" && msg.kind === "reply" && !msg.read) wrap.classList.add("is-unread");
      var who = document.createElement("div");
      who.className = "lead-bubble-meta";
      var label = msg.kind === "lead" ? "Заявка с сайта" : (msg.dir === "out" ? "Вы" : "Клиент");
      who.textContent = label + (msg.at ? " · " + fmtTime(msg.at) : "");
      var text = document.createElement("div");
      text.className = "lead-bubble-text";
      text.textContent = msg.body || "";
      wrap.appendChild(who);
      wrap.appendChild(text);
      threadBox.appendChild(wrap);
    });
    threadBox.scrollTop = threadBox.scrollHeight;
  }
  function grow() {
    if (!bodyInput) return;
    bodyInput.style.height = "auto";
    bodyInput.style.height = Math.min(160, Math.max(48, bodyInput.scrollHeight)) + "px";
  }
  function markSeen(id) {
    if (!id) return;
    var rows = threadRows(id);
    rows.forEach(function (msg) { msg.read = true; msg.notified = true; });
    var fd = new FormData();
    fd.append("csrf", csrf);
    fd.append("action", "seen");
    fd.append("lead_id", id);
    fetch("/admin/leads.php", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    }).catch(function () {});
  }
  function fill(data, keepDraft) {
    if (idInput) idInput.value = data.id || "";
    if (toInput) toInput.value = data.email || "";
    if (subjectInput) subjectInput.value = lastSubject(data.id, data.subject);
    if (bodyInput) {
      if (keepDraft && data.body) {
        bodyInput.value = data.body;
      } else {
        bodyInput.value = hasConversation(data.id) ? "" : (data.body || "");
      }
    }
    if (title) title.textContent = data.name || "Клиент";
    if (meta) meta.textContent = data.email || "";
    if (avatar) {
      var letter = (data.name || data.email || "A").trim().charAt(0).toUpperCase();
      avatar.textContent = letter || "A";
    }
    renderThread(data.id || 0);
    grow();
  }
  function open(data, keepStatus) {
    fill(data || {}, !!keepStatus);
    if (status && !keepStatus) {
      status.hidden = true;
      status.textContent = "";
    }
    modal.hidden = false;
    document.body.classList.add("modal-open");
    markSeen(data && data.id);
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
  function readLead(el) {
    var raw = el && el.getAttribute("data-lead") || "{}";
    try { return JSON.parse(raw); } catch (e) { return {}; }
  }
  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-lead-close]")) {
      event.preventDefault();
      close();
      return;
    }
    var btn = event.target.closest(".js-lead-reply");
    if (btn) {
      event.preventDefault();
      event.stopPropagation();
      open(readLead(btn));
      return;
    }
    if (event.target.closest("a, button, input, textarea, label")) return;
    var row = event.target.closest("tr.js-lead-open[data-lead]");
    if (row) {
      open(readLead(row));
    }
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !modal.hidden) close();
  });
  if (bodyInput) {
    bodyInput.addEventListener("input", grow);
    bodyInput.addEventListener("keydown", function (event) {
      if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
        event.preventDefault();
        if (form) form.requestSubmit();
      }
    });
  }
  if (form && submit) {
    form.addEventListener("submit", function () {
      submit.disabled = true;
      submit.textContent = "Отправляем…";
      if (status) {
        status.hidden = false;
        status.className = "form-note lead-chat-status";
        status.textContent = "Отправляем письмо";
      }
    });
  }
  var reopen = {$openJson};
  if (reopen && Number(reopen.id) > 0) open(reopen, true);
})();
JS;
quantlab_admin_end($replyJs);
