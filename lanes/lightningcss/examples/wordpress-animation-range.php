<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-scroll-reveal {
  animation: wp-cover-reveal 500ms ease;
  animation-range-start: entry 0%;
  animation-range-end: exit 90%;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
