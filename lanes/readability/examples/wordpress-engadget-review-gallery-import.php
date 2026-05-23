<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/engadget/source.html');
$url = 'https://www.engadget.com/2017/11/03/xbox-one-x-review/';

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, $url, true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Image payloads: ' . substr_count($article->contentHtml, '<img') . "\n";
echo 'Iframe payloads: ' . substr_count($article->contentHtml, '<iframe') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Gallery/product chrome retained: ' . (
    str_contains($blocks, '+10')
    || str_contains($blocks, '+6')
    || str_contains($blocks, 'Buy Now')
    || str_contains($blocks, 'thumbnail=130%2C87')
    || str_contains($blocks, '/products/microsoft/xbox/one/x')
        ? 'yes'
        : 'no'
) . "\n";
