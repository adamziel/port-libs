<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation-name: wp-cover-entrance;
  animation-duration: 90ms;
  animation-timing-function: ease-in-out;
  animation-delay: 100ms;
  animation-iteration-count: 2;
  animation-direction: alternate;
  animation-fill-mode: forwards;
  animation-play-state: running;
  animation-timeline: scroll();
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
