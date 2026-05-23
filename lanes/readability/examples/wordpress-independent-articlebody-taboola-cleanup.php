<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/bug-1255978/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Published time: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'ArticleBody id retained: ' . (str_contains($article->contentHtml, 'gigya-share-btns-2_gig_containerParent') ? 'yes' : 'no') . "\n";
echo 'Taboola recommendations imported: ' . (
    str_contains($blocks, 'Taboola') || str_contains($blocks, '1,000,000 are using this app')
        ? 'yes'
        : 'no'
) . "\n";
echo 'Gallery promo imported: ' . (str_contains($blocks, 'Business news in pictures') ? 'yes' : 'no') . "\n";
echo 'Reuse link retained: ' . (str_contains($blocks, 'Reuse content') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
