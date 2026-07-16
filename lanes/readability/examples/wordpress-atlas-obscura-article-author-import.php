<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/article-author-tag/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Article body root retained: ' . (str_contains($article->contentHtml, '<section id="article-body"') ? 'yes' : 'no') . "\n";
echo 'Image payloads: ' . substr_count($article->contentHtml, '<img') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Separator blocks: ' . substr_count($blocks, '<!-- wp:separator -->') . "\n";
echo 'Header/navigation chrome retained: ' . (
    str_contains($blocks, 'ArticleHeader__byline')
    || str_contains($blocks, 'July 10, 2015')
    || str_contains($blocks, 'Atlas Obscura Trips')
        ? 'yes'
        : 'no'
) . "\n";
