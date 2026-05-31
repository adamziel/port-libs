<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$entrySource = <<<'CSS'
@import "blocks.css";
.theme-footer {
  color: green;
}
CSS;

$blockSource = <<<'CSS'
/*! Theme package license */
/*!
 * Block editor stylesheet generated from theme.json
 * Keep comments for distribution compliance.
 */
.wp-block-cover {
  color: yellow;
}
.wp-block-cover .wp-block-button {
  margin: 1rem;
}
CSS;

$firstRule = '.wp-block-cover{color:#ff0}';
$secondRule = '.wp-block-cover .wp-block-button{margin:1rem}';
$layerOpen = '@layer theme.blocks{';
$layerClose = '}';
$footerRule = '.theme-footer{color:green}';
$code = "/*! Theme package license */\n"
    . "/*!\n"
    . " * Block editor stylesheet generated from theme.json\n"
    . " * Keep comments for distribution compliance.\n"
    . " */\n"
    . $layerOpen
    . $firstRule
    . $secondRule
    . $layerClose
    . $footerRule;

$blockMap = new SourceMap();
$blockSourceIndex = $blockMap->addSource('wp-content/themes/example/blocks.css');
$blockMap->setSourceContent($blockSourceIndex, $blockSource);
$blockMap->addPrinterMapping(0, 0, $blockSourceIndex, 5, 1);
$blockMap->addPrinterMapping(0, strlen($firstRule), $blockSourceIndex, 8, 1);

$map = new SourceMap();
$entrySourceIndex = $map->addSource('wp-content/themes/example/style.css');
$map->setSourceContent($entrySourceIndex, $entrySource);
$map->addVlqMap(
    $blockMap->writeVlq(),
    ['wp-content/themes/example/blocks.css'],
    [$blockSource],
    [],
    5,
    strlen($layerOpen)
);
$map->addMappingWithOffset(
    0,
    strlen($layerOpen) + strlen($firstRule) + strlen($secondRule) + strlen($layerClose),
    $entrySourceIndex,
    1,
    0,
    5,
    0
);

echo $code . "\n";
echo $map->toJson(null, false) . "\n";
