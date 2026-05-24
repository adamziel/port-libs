<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/ol/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Ordered list blocks: ' . substr_count($blocks, '<!-- wp:list {"ordered":true} -->') . "\n";
echo 'Paragraph-wrapped ordered lists: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<ol>") ? 'yes' : 'no') . "\n";
