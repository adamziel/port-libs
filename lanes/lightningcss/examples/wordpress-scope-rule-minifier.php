<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@scope (.wp-block-group.is-style-card) to (.wp-block-buttons) {
  .wp-block-heading {
    color: yellow;
  }

  .wp-block-image img {
    border-color: blue;
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
