<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@supports (width: calc(10px * 2)) {
  .wp-block-gallery {
    width: calc(10px * 2);
  }
}

@supports (color: hsl(0deg, 0%, 0%)) {
  .wp-block-post-title {
    color: hsl(0deg, 0%, 0%);
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
