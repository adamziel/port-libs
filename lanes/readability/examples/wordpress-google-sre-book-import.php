<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/google-sre-book-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

$byline = trim(preg_replace('/\s+/', ' ', $article->byline ?? 'null') ?? 'null');

echo "Title: {$article->title}\n";
echo "Byline: {$byline}\n";
echo 'Chapter root retained: ' . (str_contains($article->contentHtml, '<section data-type="chapter" id="maia-main" role="main"') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Table blocks: ' . substr_count($blocks, '<!-- wp:table -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Book navigation/header chrome retained: ' . (
    str_contains($blocks, 'Table of Contents')
    || str_contains($blocks, 'Part I - Introduction')
    || str_contains($blocks, 'Chapter 6 - Monitoring Distributed Systems')
    || str_contains($blocks, 'lh3.googleusercontent.com')
        ? 'yes'
        : 'no'
) . "\n";
