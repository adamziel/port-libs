<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-image.is-style-square {
  aspect-ratio: 1 / 1;
}

.wp-block-video.is-style-hero {
  aspect-ratio: auto 16 / 9;
}

.wp-block-post-featured-image img {
  aspect-ratio: 3 / 2 auto;
  object-fit: cover;
}
CSS;

$expected = '.wp-block-image.is-style-square{aspect-ratio:1/1}.wp-block-video.is-style-hero{aspect-ratio:auto 16/9}.wp-block-post-featured-image img{aspect-ratio:auto 3/2;object-fit:cover}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
