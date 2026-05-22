<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\CssMinifier;

$css = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.css');

echo (new CssMinifier())->minify($css) . PHP_EOL;
