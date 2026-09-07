<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

quantlab_write_seo_files();
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo quantlab_sitemap_xml();
