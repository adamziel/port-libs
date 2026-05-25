<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\CustomMediaTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@custom-media --wp-mobile (max-width: 599px);
@custom-media --wp-motion (prefers-reduced-motion: no-preference);
@custom-media --wp-wide (min-width: 782px);

@import url(./blocks/query-card.css) /* wp block layer */ layer(theme.blocks) supports((display: grid)) screen and (--wp-wide);

@media (--wp-mobile) and (--wp-motion) {
  .wp-block-cover.is-style-animated {
    animation-duration: 100ms;
    color: yellow;
  }
}
CSS;

$transformed = (new CustomMediaTransformer())->transform($css);

echo (new CssMinifier())->minify($transformed) . PHP_EOL;
