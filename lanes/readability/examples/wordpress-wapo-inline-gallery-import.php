<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/wapo-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Video caption retained: ' . (str_contains($blocks, 'Gunmen in military uniforms stormed Tunisia') ? 'yes' : 'no') . "\n";
echo 'Map graphic retained: ' . (str_contains($blocks, 'tunisia600.jpg') ? 'yes' : 'no') . "\n";
echo 'Gallery chrome retained: ' . (
    str_contains($blocks, 'View Photos')
    || str_contains($blocks, 'Full Screen')
    || str_contains($blocks, 'Buy Photo')
        ? 'yes'
        : 'no'
) . "\n";
