<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@container wp-query-card (inline-size > 45em) {
  .wp-block-post-template {
    gap: calc(1cqw + 2cqw);
  }
}

@container wp-query-card (inline-size > 45em) {
  .wp-block-post-template {
    color: yellow;
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
