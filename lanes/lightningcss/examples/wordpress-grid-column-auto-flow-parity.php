<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query.is-style-offset-area {
  grid-template-areas: ". title title ." ". card card .";
  grid-template-rows: auto 1fr;
  grid-template-columns: 10px minmax(0, 1fr) minmax(0, 1fr) 10px;
}

.wp-block-query.is-style-auto-column {
  grid: 300px / auto-flow;
  grid-template-areas: " . post . ";
}

.wp-block-query.is-style-auto-column-fill {
  grid: 200px 1fr / auto-flow auto;
  grid-template-areas: " . excerpt . ";
}

.wp-block-query.is-style-auto-column-dense {
  grid: 1fr 2fr / dense auto-flow;
  grid-template-areas: " . card . ";
}

.wp-block-group.is-style-repeat-areas {
  grid-template-areas: "header header header" "main main aside";
  grid-template-columns: auto 1fr auto;
  grid-template-rows: repeat(2, 1fr);
}
CSS;

$expected = '.wp-block-query.is-style-offset-area{grid-template:".titletitle."".cardcard."1fr/10px minmax(0,1fr) minmax(0,1fr) 10px}.wp-block-query.is-style-auto-column{grid:300px/auto-flow;grid-template-areas:".post."}.wp-block-query.is-style-auto-column-fill{grid:200px 1fr/auto-flow;grid-template-areas:".excerpt."}.wp-block-query.is-style-auto-column-dense{grid:1fr 2fr/auto-flow dense;grid-template-areas:".card."}.wp-block-group.is-style-repeat-areas{grid-template-areas:"header header header""main main aside";grid-template-columns:auto 1fr auto;grid-template-rows:repeat(2,1fr)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected grid column auto-flow parity output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
