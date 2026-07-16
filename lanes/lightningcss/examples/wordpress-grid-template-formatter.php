<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover__inner-container {
  grid-template: [hero-start]"media media"[hero-end content-start]"copy actions" minmax(12rem, 1fr)[content-end]/minmax(0,1fr) auto;
}

.wp-block-query.is-style-archive-grid {
  grid-template-areas: "title title" "meta excerpt";
  grid-template-rows: auto 1fr;
  grid-template-columns: minmax(0, 1fr) auto;
}

.wp-block-gallery.is-style-masonry {
  grid-template-areas: none;
  grid-template-rows: none;
  grid-template-columns: minmax(12rem, 1fr) minmax(12rem, 1fr);
  grid-auto-flow: row dense;
  grid-auto-rows: 1fr;
  grid-auto-columns: auto;
}
CSS;

$expected = <<<'CSS'
.wp-block-cover__inner-container {
  grid-template: [hero-start] "media media" [hero-end]
                 [content-start] "copy actions" minmax(12rem, 1fr) [content-end]
                 / minmax(0,1fr) auto;
}

.wp-block-query.is-style-archive-grid {
  grid-template: "title title"
                 "meta excerpt" 1fr
                 / minmax(0, 1fr) auto;
}

.wp-block-gallery.is-style-masonry {
  grid: auto-flow dense 1fr / minmax(12rem, 1fr) minmax(12rem, 1fr);
}

CSS;

$actual = (new CssFormatter())->format($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected formatted CSS:\n" . $actual . "\n");
    exit(1);
}

echo $actual;
