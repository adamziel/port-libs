<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover__inner-container {
  grid-template: [hero-start]"media media"[hero-end content-start]"copy actions" minmax(12rem, 1fr)[content-end]/minmax(0,1fr) auto;
}
CSS;

$expected = <<<'CSS'
.wp-block-cover__inner-container {
  grid-template: [hero-start] "media media" [hero-end]
                 [content-start] "copy actions" minmax(12rem, 1fr) [content-end]
                 / minmax(0,1fr) auto;
}

CSS;

$actual = (new CssFormatter())->format($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected formatted CSS:\n" . $actual . "\n");
    exit(1);
}

echo $actual;
