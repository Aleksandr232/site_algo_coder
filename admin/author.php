<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_require();

$tgReady = function_exists('quantlab_telegram_enabled') && quantlab_telegram_enabled();
$error = '';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quantlab_csrf_check();
    if (!$tgReady) {
        $error = 'Сначала TELEGRAM_BOT_TOKEN и TELEGRAM_CHANNEL_ID в .env, бот — админ канала с правом закреплять.';
    } else {
        $pin = !empty($_POST['pin']);
        $result = quantlab_telegram_share_author($pin);
        if (!empty($result['ok'])) {
            $qs = !empty($result['pinned']) ? 'sent=1&pin=1' : 'sent=1';
            if (($result['error'] ?? '') !== '') {
                $qs .= '&warn=1';
            }
            header('Location: /admin/author.php?' . $qs, true, 302);
            exit;
        }
        $error = (string) ($result['error'] ?? 'Не удалось отправить в канал');
    }
}

$sent = (string) ($_GET['sent'] ?? '') === '1';
$pinnedOk = (string) ($_GET['pin'] ?? '') === '1';
$warn = (string) ($_GET['warn'] ?? '') === '1';
$state = function_exists('quantlab_telegram_author_state') ? quantlab_telegram_author_state() : [];
$caption = function_exists('quantlab_telegram_author_caption') ? quantlab_telegram_author_caption() : '';
$preview = trim(html_entity_decode(strip_tags(str_replace(['<b>', '</b>'], '', $caption)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$author = function_exists('quantlab_author') ? quantlab_author() : [];
$authorUrl = function_exists('quantlab_telegram_author_url') ? quantlab_telegram_author_url() : '/#author';
$lastAt = '';
if (!empty($state['at'])) {
    $ts = strtotime((string) $state['at']);
    $lastAt = $ts ? date('d.m.Y H:i', $ts) : (string) $state['at'];
}

quantlab_admin_start('Автор в Telegram — админка AM QuantLab');
?>
        <?= quantlab_render_crumbs([
            ['name' => 'Главная', 'path' => '/'],
            ['name' => 'Админка', 'path' => '/admin/'],
            ['name' => 'Автор', 'path' => '/admin/author.php'],
        ]) ?>
        <div class="admin-head">
          <div>
            <p class="eyebrow">Админка</p>
            <h1>Пост про автора</h1>
            <p class="lead">В канал уйдёт фото, текст без цен и кнопка на <a href="<?= quantlab_h($authorUrl) ?>"><?= quantlab_h($authorUrl) ?></a>. Можно сразу закрепить.</p>
            <?= quantlab_admin_telegram_note() ?>
          </div>
          <a class="btn btn-ghost" href="/#author" target="_blank" rel="noopener">Блок на сайте</a>
        </div>

        <?php if ($sent): ?>
          <p class="form-note" style="display:block">
            <?php if ($pinnedOk): ?>
              Пост ушёл в канал и закреплён.
            <?php elseif ($warn): ?>
              Пост ушёл, закреп не вышел. Проверьте, что бот может закреплять сообщения.
            <?php else: ?>
              Пост ушёл в канал.
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <p class="form-note" style="display:block"><?= quantlab_h($error) ?></p>
        <?php endif; ?>

        <div class="glass pad" style="margin-bottom:20px">
          <p class="eyebrow">Как уйдёт в канал</p>
          <?php if (!empty($author['photo'])): ?>
            <img class="admin-thumb" src="<?= quantlab_h((string) $author['photo']) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:16px;margin:8px 0 16px" />
          <?php endif; ?>
          <p style="white-space:pre-wrap"><?= quantlab_h($preview) ?></p>
          <p class="field-hint">Кнопка: «Кто пишет роботов» → блок автора. Цены в подпись не попадают.</p>
          <?php if ($lastAt !== ''): ?>
            <p class="field-hint">Последняя отправка: <?= quantlab_h($lastAt) ?><?= !empty($state['pinned']) ? ', был закреп' : '' ?>.</p>
          <?php endif; ?>
        </div>

        <form class="admin-form" method="post">
          <input type="hidden" name="csrf" value="<?= quantlab_h(quantlab_csrf_token()) ?>" />
          <label class="check-row">
            <input type="checkbox" name="pin" value="1" checked <?= $tgReady ? '' : 'disabled' ?> />
            Закрепить в канале
          </label>
          <p class="hero-actions">
            <button class="btn" type="submit" <?= $tgReady ? '' : 'disabled' ?> onclick="return confirm('Отправить пост про автора в канал?');">Опубликовать в Telegram</button>
          </p>
          <?php if (!$tgReady): ?>
            <span class="field-hint">Сначала TELEGRAM_BOT_TOKEN в .env и бот-админ канала с правом закреплять сообщения.</span>
          <?php endif; ?>
        </form>
<?php
quantlab_admin_end();
