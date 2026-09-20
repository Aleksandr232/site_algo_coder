<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$error = '';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'reply') {
    quantlab_csrf_check();
    $id = (int) ($_POST['lead_id'] ?? 0);
    $lead = quantlab_lead_by_id($id);
    if (!$lead) {
        $error = 'Заявка не найдена';
    } elseif (!quantlab_mail_enabled()) {
        $error = 'Сначала SMTP в .env — письмо должно уйти с info@amquantlab.ru';
    } else {
        try {
            $sent = quantlab_lead_reply_mail(
                $lead,
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['subject'] ?? ''))
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
                  <th>Ответ с почты</th>
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
                    $defaultSubject = !empty($lead['robot_title'])
                        ? ('AM QuantLab: по заявке «' . $lead['robot_title'] . '»')
                        : 'AM QuantLab: по вашей заявке';
                    $defaultBody = ($name !== '' ? ('Здравствуйте, ' . $name . '.') : 'Здравствуйте.') . "\n\n";
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
                        <form class="lead-reply-form" method="post">
                          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
                          <input type="hidden" name="action" value="reply" />
                          <input type="hidden" name="lead_id" value="<?= $leadId ?>" />
                          <input type="text" name="subject" value="<?= quantlab_h($defaultSubject) ?>" required />
                          <textarea name="body" rows="4" required placeholder="Текст письма клиенту"><?= quantlab_h($defaultBody) ?></textarea>
                          <button class="btn btn-sm" type="submit">Отправить с <?= quantlab_h($fromBox) ?></button>
                        </form>
                        <?php if ($lastAt !== ''): ?>
                          <span class="field-hint">Уже писали <?= quantlab_h($lastAt) ?></span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
<?php
quantlab_admin_end();
