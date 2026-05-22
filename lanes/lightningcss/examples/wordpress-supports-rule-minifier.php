<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@supports (((display: grid) and (not (display: subgrid)))) {
  .wp-block-query > .wp-block-post-template {
    display: grid;
    color: yellow;
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
