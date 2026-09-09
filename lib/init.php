<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'site.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'blog.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'seo.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'strategies.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'mail.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'leads.php';

$quantlabPdo = quantlab_db();
if ($quantlabPdo) {
    quantlab_db_import_json_posts($quantlabPdo);
    quantlab_db_import_json_redirects($quantlabPdo);
    quantlab_strategies_seed_if_empty();
}
