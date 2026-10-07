<?php

declare(strict_types=1);

function quantlab_turnstile_site_key(): string
{
    return quantlab_env('TURNSTILE_SITE_KEY');
}

function quantlab_turnstile_enabled(): bool
{
    return quantlab_turnstile_site_key() !== '' && quantlab_env('TURNSTILE_SECRET_KEY') !== '';
}

function quantlab_turnstile_widget(): void
{
    if (!quantlab_turnstile_enabled()) {
        return;
    }
    static $script = false;
    $key = quantlab_h(quantlab_turnstile_site_key());
    echo '<div class="cf-turnstile" data-sitekey="' . $key . '" data-theme="dark" data-language="ru" data-appearance="always"></div>';
    if ($script) {
        return;
    }
    $script = true;
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    echo '<script>window.quantlabResetTurnstile=function(form){if(!form||!window.turnstile)return;var node=form.querySelector(".cf-turnstile");if(!node)return;try{window.turnstile.reset(node);}catch(e){try{window.turnstile.reset();}catch(e2){}}};</script>';
}

function quantlab_turnstile_assert(): void
{
    if (!quantlab_turnstile_enabled()) {
        return;
    }
    $token = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '' || strlen($token) > 2048) {
        throw new InvalidArgumentException('Подтвердите, что вы не робот');
    }
    $ip = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($ip === '') {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        throw new InvalidArgumentException('Проверка Cloudflare не пройдена. Обновите страницу и попробуйте ещё раз.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => quantlab_env('TURNSTILE_SECRET_KEY'),
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || empty($data['success'])) {
        throw new InvalidArgumentException('Проверка Cloudflare не пройдена. Обновите страницу и попробуйте ещё раз.');
    }
}
