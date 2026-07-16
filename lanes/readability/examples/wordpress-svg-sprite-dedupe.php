<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$sprite = '<svg class="theme-icons"><symbol id="wp-play" viewBox="0 0 10 10"><path d="M0 0 L10 5 L0 10 Z"></path></symbol></svg>';
$html = '<html><head><meta property="og:title" content="SVG Sprite Import"></head><body><article>'
    . '<h1>SVG Sprite Import</h1>'
    . '<p>' . str_repeat('Legacy WordPress themes can embed repeated inline SVG symbol sprites inside exported post content. ', 3) . '</p>'
    . $sprite
    . '<p>' . str_repeat('The migration should keep one reusable sprite while preventing duplicate icon blocks in the imported article. ', 3) . '</p>'
    . '<svg id="editorial-diagram" viewBox="0 0 20 20"><path d="M0 0 L20 20"></path></svg>'
    . $sprite
    . '</article></body></html>';

$extractor = new ArticleExtractor();
$article = $extractor->extract($html);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
