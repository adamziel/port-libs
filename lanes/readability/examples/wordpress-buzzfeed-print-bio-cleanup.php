<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/buzzfeed-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Inline images retained in paragraph flow: ' . (str_contains($blocks, 'grid-cell-2501-1429608056-15.jpg') ? 'yes' : 'no') . "\n";
echo 'Print/bio/share chrome retained: ' . (
    str_contains($blocks, 'View this image')
    || str_contains($blocks, 'Check out more articles on BuzzFeed.com')
    || str_contains($blocks, 'Mark di Stefano is a breaking news reporter')
    || str_contains($blocks, 'More ')
        ? 'yes'
        : 'no'
) . "\n";
