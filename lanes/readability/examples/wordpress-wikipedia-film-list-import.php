<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/wikipedia-4/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Table rows: ' . substr_count($article->contentHtml, '<tr') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Table blocks: ' . substr_count($blocks, '<!-- wp:table -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Wikipedia chrome retained: ' . (
    str_contains($blocks, 'dynamic list')
    || str_contains($blocks, 'Special:CentralAutoLogin')
    || str_contains($blocks, 'Categories:')
    || str_contains($blocks, 'Film portal')
        ? 'yes'
        : 'no'
) . "\n";
