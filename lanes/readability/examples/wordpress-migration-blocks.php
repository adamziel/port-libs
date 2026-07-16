<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$extractor = new ArticleExtractor();
$article = $extractor->extract((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-page-builder.html'));

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
