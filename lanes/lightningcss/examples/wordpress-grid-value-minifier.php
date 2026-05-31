<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query .wp-block-post-template {
  grid-template-columns: repeat(auto-fill, [card-start] minmax(12rem, 1fr) [card-end]);
  grid-auto-rows: minmax(100px, auto) 0.5fr;
  grid-auto-flow: dense row;
}

.wp-block-query.is-layout-grid {
  grid-template-areas: "title title" "image ....";
  grid-area: content / content / content / content;
}

.wp-block-cover__inner-container {
  grid-template: [top] "media   media" [middle] [content] "text   buttons" 1fr [bottom] / minmax(0, 1fr) auto;
}
CSS;

$expected = '.wp-block-query .wp-block-post-template{grid-template-columns:repeat(auto-fill,[card-start]minmax(12rem,1fr)[card-end]);grid-auto-rows:minmax(100px,auto) .5fr;grid-auto-flow:dense}.wp-block-query.is-layout-grid{grid-template-areas:"title title""image.";grid-area:content}.wp-block-cover__inner-container{grid-template:[top]"media media"[middle content]"text buttons"1fr[bottom]/minmax(0,1fr) auto}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
