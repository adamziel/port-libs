<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation-duration: 100ms, 2000ms;
  animation-delay: 100ms;
  animation-iteration-count: 2.0, infinite;
  animation-fill-mode: Backwards,forwards;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
