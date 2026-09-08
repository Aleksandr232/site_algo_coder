<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: index, follow, max-snippet:-1');
echo quantlab_llms_txt();
