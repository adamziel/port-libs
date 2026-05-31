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

$conjunctionBundle = (new CssBundler())->bundle('/conjunction.css', [
    '/conjunction.css' => <<<'CSS'
@import "query.css" layer(theme.blocks) all;
.wp-site-blocks {
  color: red;
}
CSS,
    '/query.css' => <<<'CSS'
@import "wide.css" only screen;
.wp-block-query {
  color: blue;
}
CSS,
    '/wide.css' => <<<'CSS'
.wp-block-query-card {
  color: green;
}
CSS,
]);

$expectedConjunction = '@media only screen{@layer theme.blocks{.wp-block-query-card{color:green}}}@layer theme.blocks{.wp-block-query{color:#00f}}.wp-site-blocks{color:red}';

if ($conjunctionBundle !== $expectedConjunction) {
    fwrite(STDERR, "Unexpected media query conjunction import graph output:\n{$conjunctionBundle}\n");
    exit(1);
}

echo $bundle . PHP_EOL;
echo $conjunctionBundle . PHP_EOL;
echo 'media-range-layer-import-graph: bundled' . PHP_EOL;
