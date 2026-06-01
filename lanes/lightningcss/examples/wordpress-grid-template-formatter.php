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

CSS;

$actual = (new CssFormatter())->format($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected formatted CSS:\n" . $actual . "\n");
    exit(1);
}

echo $actual;
