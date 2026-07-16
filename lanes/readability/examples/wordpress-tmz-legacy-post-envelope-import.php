<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/tmz-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Legacy date retained: ' . (str_contains($blocks, '2/26/2015 7:11 AM PST BY TMZ STAFF') ? 'yes' : 'no') . "\n";
echo 'Article body retained: ' . (str_contains($blocks, 'now-famous Oscar dress') ? 'yes' : 'no') . "\n";
echo 'Duplicate split headline retained: ' . (str_contains($blocks, '<h2>Lupita Nyong') ? 'yes' : 'no') . "\n";
echo 'Inline images retained: ' . substr_count($article->contentHtml, '<img') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
