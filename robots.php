<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
header('Content-Type: text/plain; charset=utf-8');
echo quantlab_robots_txt();
