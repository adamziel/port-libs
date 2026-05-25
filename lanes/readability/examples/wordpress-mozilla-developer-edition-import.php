<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/mozilla-2/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Readerable: ' . ($extractor->isProbablyReaderable($source) ? 'yes' : 'no') . "\n";
echo 'Feature copy retained: ' . (str_contains($blocks, 'Features and tools') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Head comment chrome retained: ' . (str_contains($blocks, 'Interested in having a direct impact') ? 'yes' : 'no') . "\n";
