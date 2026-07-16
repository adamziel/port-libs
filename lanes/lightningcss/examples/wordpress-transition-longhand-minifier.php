<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button.is-style-animated .wp-element-button {
  transition-duration: calc((1s - 50ms) * 2);
  transition-delay: 500ms;
  transition-timing-function: cubic-bezier(0.42, 0, 1, 1), steps(5, jump-start);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
