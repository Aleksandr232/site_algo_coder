<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$leads = quantlab_leads_all();
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
            <p class="lead">Клиенты с главной страницы. <?= count($leads) ?> шт.</p>
            <?= quantlab_admin_storage_note() ?>
            <?= quantlab_admin_mail_note() ?>
          </div>
          <a class="btn btn-ghost" href="/admin/">К статьям</a>
        </div>

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
                  <th>Рынок</th>
                  <th>Задача</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leads as $lead): ?>
                  <?php
                    $ts = strtotime((string) ($lead['created_at'] ?? ''));
                    $when = $ts ? date('d.m.Y H:i', $ts) : (string) ($lead['created_at'] ?? '');
                  ?>
                  <tr>
                    <td><?= quantlab_h($when) ?></td>
                    <td><?= quantlab_h((string) ($lead['name'] ?? '')) ?></td>
                    <td><?= quantlab_h((string) ($lead['contact'] ?? '')) ?></td>
                    <td><?= quantlab_h(quantlab_lead_market_label((string) ($lead['market'] ?? ''))) ?></td>
                    <td class="lead-msg"><?= quantlab_h((string) ($lead['message'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
<?php
quantlab_admin_end();
