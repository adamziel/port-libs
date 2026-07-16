<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button.is-style-slide .wp-element-button {
  transition: ease-in 1s transform 400ms, opacity 1000ms cubic-bezier(0.25, 0.1, 0.25, 1);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
