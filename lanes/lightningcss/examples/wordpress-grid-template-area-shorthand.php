<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query.is-style-magazine {
  grid-template: 64px auto 1fr / 1fr 2fr 1fr;
  grid-template-areas:
    "hero hero meta"
    "hero hero aside";
}

.wp-block-gallery.is-style-masonry {
  grid: auto / 1fr 3fr;
  grid-template-rows: 20px;
  grid-template-areas: ". content .";
}
CSS;

$actual = (new CssMinifier())->minify($css);
$expected = '.wp-block-query.is-style-magazine{grid-template:"hero hero meta"64px"hero hero aside"". . ."1fr/1fr 2fr 1fr}.wp-block-gallery.is-style-masonry{grid:".content."20px/1fr 3fr}';

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected grid shorthand area minification:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
