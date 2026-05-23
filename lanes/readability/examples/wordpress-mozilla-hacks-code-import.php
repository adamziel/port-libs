<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/002/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Content root retained: ' . (str_contains($article->contentHtml, 'id="content-main"') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Code blocks: ' . substr_count($blocks, '<!-- wp:code -->') . "\n";
echo 'Comment/sidebar chrome retained: ' . (
    str_contains($blocks, '2 comments')
    || str_contains($blocks, 'Read more articles by Nikhil Marathe')
    || str_contains($blocks, 'Except where otherwise noted')
        ? 'yes'
        : 'no'
) . "\n";
