<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@charset "UTF-8";
@layer blocks;
@import url(./blocks/query-card.css) supports((display: grid)) screen and (min-width: 600px);
@import u\72l(./blocks/navigation.css) l\61yer(blocks.navigation) s\75pports(display: flex) screen and (min-width: 782px);

.wp-block-query {
  color: yellow;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
