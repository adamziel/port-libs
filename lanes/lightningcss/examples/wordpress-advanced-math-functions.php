<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-depth {
  outline-offset: abs(-4px);
  margin-block-start: calc(1px * hypot(3, 4));
  padding-block: calc(1rem * pow(2, 2));
  translate: 0 calc(10px * sign(-1vw));
  width: calc(100% + 10px * sign(1%));
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
