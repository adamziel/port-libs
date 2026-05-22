<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation: "wp-cover-entrance" 3s cubic-bezier(0.42, 0, 1, 1) 100ms 2.0 alternate Backwards running scroll(block);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
