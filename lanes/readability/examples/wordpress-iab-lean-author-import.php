<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/iab-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published time: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Header date imported as block text: ' . (str_contains($blocks, '10.15.15') ? 'yes' : 'no') . "\n";
echo 'Header hero image imported: ' . (str_contains($blocks, 'getting-lean-with-digital-ad-ux-2-1000x305.jpg') ? 'yes' : 'no') . "\n";
echo 'Author bio retained: ' . (str_contains($blocks, 'Scott Cunningham') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
