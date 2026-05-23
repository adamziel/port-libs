<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-gallery.is-style-tilt .wp-block-image {
  transform: translateX(calc(2in + 50px)) rotate3d(0, 1, 0, 15deg) skew(0deg, 3deg);
  rotate: 10deg 0 0 -1;
  scale: 100% 105% 1;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
