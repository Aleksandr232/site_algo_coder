<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

header('Cache-Control: no-store');

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

function quantlab_lead_reply(bool $ok, string $message, int $status, bool $json): void
{
    if ($json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $ok ? null : $message, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        $slug = trim((string) ($_POST['robot_slug'] ?? ''));
        if ($slug !== '' && function_exists('quantlab_is_slug') && quantlab_is_slug($slug)) {
            header('Location: ' . quantlab_public_path('robots/' . $slug) . '?sent=1', true, 302);
            exit;
        }
        header('Location: /?sent=1#contact', true, 302);
        exit;
    }
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    quantlab_lead_reply(false, 'Метод не поддерживается', 405, $wantsJson);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    quantlab_lead_reply(true, 'Заявка принята', 200, $wantsJson);
}

try {
    quantlab_lead_save($_POST);
    quantlab_lead_reply(true, 'Заявка сохранена', 200, $wantsJson);
} catch (InvalidArgumentException $e) {
    quantlab_lead_reply(false, $e->getMessage(), 400, $wantsJson);
} catch (Throwable $e) {
    quantlab_lead_reply(false, $e->getMessage(), 429, $wantsJson);
}
