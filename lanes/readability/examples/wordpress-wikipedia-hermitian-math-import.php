<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/wikipedia-3/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $article->contentHtml . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Math/editorial images retained: ' . ($xpath->query('//img')?->length ?? 0) . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Table blocks: ' . substr_count($blocks, '<!-- wp:table -->') . "\n";
echo 'MediaWiki shell chrome retained: ' . (
    str_contains($blocks, 'From Wikipedia, the free encyclopedia')
    || str_contains($blocks, 'Jump to navigation')
    || str_contains($blocks, 'Jump to search')
    || str_contains($blocks, 'Special:CentralAutoLogin')
    || str_contains($blocks, 'Categories:')
        ? 'yes'
        : 'no'
) . "\n";
