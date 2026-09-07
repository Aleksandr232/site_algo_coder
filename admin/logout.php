<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_admin_logout();
header('Location: /admin/login.php', true, 302);
exit;
