<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$source = <<<'CSS'
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
$code = "/*! Theme package license */\n"
    . "/*!\n"
    . " * Block editor stylesheet generated from theme.json\n"
    . " * Keep comments for distribution compliance.\n"
    . " */\n"
    . $firstRule
    . $secondRule;

$map = new SourceMap();
$sourceIndex = $map->addSource('wp-content/themes/example/blocks.css');
$map->setSourceContent($sourceIndex, $source);
$map->addPrinterMapping(5, 0, $sourceIndex, 5, 1);
$map->addPrinterMapping(5, strlen($firstRule), $sourceIndex, 8, 1);

echo $code . "\n";
echo $map->toJson(null, false) . "\n";
