<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query.is-style-magazine-grid {
  grid: "hero hero" minmax(100px, max-content) "body rail" 1fr / auto-flow dense 40%;
}

.wp-block-query.is-style-auto-track {
  grid: auto-flow 300px / repeat(3, [card-start card-end] 200px);
}

.wp-block-group.is-style-sidebar-grid {
  grid: auto-flow / minmax(0, 1fr) 20rem;
}

.wp-block-cover.is-style-named-line-grid {
  grid: [content-start] minmax(20em, max-content) / auto-flow dense 40%;
}
CSS;

$expected = '.wp-block-query.is-style-magazine-grid{grid:"hero hero"minmax(100px,max-content)"body rail"1fr/auto-flow dense 40%}.wp-block-query.is-style-auto-track{grid:auto-flow 300px/repeat(3,[card-start card-end]200px)}.wp-block-group.is-style-sidebar-grid{grid:none/minmax(0,1fr) 20rem}.wp-block-cover.is-style-named-line-grid{grid:[content-start]minmax(20em,max-content)/auto-flow dense 40%}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
