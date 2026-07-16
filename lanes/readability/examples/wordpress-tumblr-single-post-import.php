<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/tumblr/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Published time: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Release notes retained: ' . (str_contains($blocks, 'Removed Herobrine') ? 'yes' : 'no') . "\n";
echo 'Theme sidebar retained: ' . (
    str_contains($blocks, 'Official links:')
    || str_contains($blocks, 'Community links:')
    || str_contains($blocks, 'Powered by Tumblr')
        ? 'yes'
        : 'no'
) . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
