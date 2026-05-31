<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bundle = (new CssBundler())->bundle('/theme.css', [
    '/theme.css' => <<<'CSS'
@import "blocks/query.css" layer(theme.blocks) print, screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/query.css' => <<<'CSS'
@import "../shared/query-card.css" (min-width: 250px), (hover);
.wp-block-query {
  color: blue;
}
CSS,
    '/shared/query-card.css' => <<<'CSS'
.wp-block-query-card {
  color: green;
}
CSS,
]);

$expected = '@media print and (width>=250px) and (hover),screen and (width>=250px) and (hover){@layer theme.blocks{.wp-block-query-card{color:green}}}@media print,screen{@layer theme.blocks{.wp-block-query{color:#00f}}}.wp-site-blocks{color:red}';

if ($bundle !== $expected) {
    fwrite(STDERR, "Unexpected media range layer import graph output:\n{$bundle}\n");
    exit(1);
}

echo $bundle . PHP_EOL;
echo 'media-range-layer-import-graph: bundled' . PHP_EOL;
