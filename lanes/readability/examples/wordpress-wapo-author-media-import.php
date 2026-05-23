<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/wapo-2/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Lead media retained: ' . (str_contains($blocks, 'Nic6429750-1140.jpg') ? 'yes' : 'no') . "\n";
echo 'Author bio retained: ' . (str_contains($blocks, 'Steven Mufson covers the White House') ? 'yes' : 'no') . "\n";
echo 'Byline/share chrome retained: ' . (
    str_contains($blocks, 'Follow @StevenMufson')
    || str_contains($blocks, 'Share on Facebook')
    || str_contains($blocks, 'Show Comments')
        ? 'yes'
        : 'no'
) . "\n";
