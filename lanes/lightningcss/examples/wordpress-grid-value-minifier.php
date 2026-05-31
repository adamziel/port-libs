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

.wp-block-group.is-style-editorial-grid {
  grid: "feature  feature" minmax(100px, max-content) "content sidebar" 1fr / auto-flow dense 40%;
}

.wp-block-group.is-style-simple-columns {
  grid: auto-flow / minmax(0, 1fr);
}

.wp-block-query.is-style-auto-placement {
  grid-template-areas: none;
  grid-template-rows: none;
  grid-template-columns: minmax(0, 1fr) 18rem;
  grid-auto-flow: row dense;
  grid-auto-rows: minmax(12rem, auto);
  grid-auto-columns: auto;
}

.wp-block-query .wp-block-post.is-featured {
  grid-row-start: feature;
  grid-row-end: feature;
  grid-column-start: feature;
  grid-column-end: feature;
}

.wp-block-query.is-style-archive-layout {
  grid-template-areas: "title title" "meta excerpt";
  grid-template-rows: auto 1fr;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-auto-flow: row;
  grid-auto-rows: auto;
  grid-auto-columns: auto;
}

.wp-block-query.is-style-featured-grid {
  grid: auto / minmax(0, 1fr) 20rem;
  grid-template-rows: auto minmax(12rem, 1fr);
  grid-template-areas: "title title" "content image";
}
CSS;

$expected = '.wp-block-query .wp-block-post-template{grid-template-columns:repeat(auto-fill,[card-start]minmax(12rem,1fr)[card-end]);grid-auto-rows:minmax(100px,auto) .5fr;grid-auto-flow:dense}.wp-block-query.is-layout-grid{grid-template-areas:"title title""image.";grid-area:content}.wp-block-cover__inner-container{grid-template:[top]"media media"[middle content]"text buttons"1fr[bottom]/minmax(0,1fr) auto}.wp-block-group.is-style-editorial-grid{grid:"feature feature"minmax(100px,max-content)"content sidebar"1fr/auto-flow dense 40%}.wp-block-group.is-style-simple-columns{grid:none/minmax(0,1fr)}.wp-block-query.is-style-auto-placement{grid:auto-flow dense minmax(12rem,auto)/minmax(0,1fr) 18rem}.wp-block-query .wp-block-post.is-featured{grid-area:feature}.wp-block-query.is-style-archive-layout{grid:"title title""meta excerpt"1fr/minmax(0,1fr) auto}.wp-block-query.is-style-featured-grid{grid:"title title""content image"minmax(12rem,1fr)/minmax(0,1fr) 20rem}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
