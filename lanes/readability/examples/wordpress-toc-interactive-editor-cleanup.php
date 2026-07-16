<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/toc-missing/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://hakibenita.com/sql-anomaly-detection');
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'TOC retained: ' . (str_contains($blocks, 'Table of Contents') ? 'yes' : 'no') . "\n";
echo 'External editor CTA retained: ' . (str_contains($blocks, 'To follow along with the article') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Code blocks: ' . substr_count($blocks, '<!-- wp:code -->') . "\n";
