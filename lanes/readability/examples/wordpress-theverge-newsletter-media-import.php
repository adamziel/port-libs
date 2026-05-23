<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/theverge/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Content wrapper retained: ' . (str_contains($article->contentHtml, 'id="content"') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Newsletter copy retained: ' . (str_contains($blocks, 'Command Line') ? 'yes' : 'no') . "\n";
echo 'Action/ad chrome retained: ' . (
    str_contains($blocks, 'SUBSCRIBE')
    || str_contains($blocks, 'Go to comments')
    || str_contains($blocks, 'Advertiser Content From')
        ? 'yes'
        : 'no'
) . "\n";
