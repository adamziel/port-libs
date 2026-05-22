<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query {
  container-name: wp-query-card;
  container-type: inline-size;
}

@container wp-query-card (inline-size > 45em) and style(--wp--custom--dense: true) {
  .wp-block-post-template {
    color: yellow;
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
