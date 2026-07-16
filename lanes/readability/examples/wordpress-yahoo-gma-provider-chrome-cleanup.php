<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/yahoo-3/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Language: ' . ($article->lang ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Related editorial links retained: ' . (str_contains($blocks, 'Pizza Man Making Special Delivery') ? 'yes' : 'no') . "\n";
echo 'Provider/action chrome retained: ' . (
    str_contains($blocks, 'Share Your Recipe')
    || str_contains($blocks, 'Good Morning America')
    || str_contains($blocks, 'More like this')
    || str_contains($blocks, 'Fewer like this')
        ? 'yes'
        : 'no'
) . "\n";
