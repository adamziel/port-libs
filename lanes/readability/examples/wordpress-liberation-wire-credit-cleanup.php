<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/liberation-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Language: ' . ($article->lang ?? 'null') . "\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Published time: ' . ($article->publishedTime ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Dailymotion embed retained: ' . (str_contains($blocks, 'dailymotion.com/embed/video/x2oikl3') ? 'yes' : 'no') . "\n";
echo 'Trailing wire credit retained: ' . (
    str_contains($article->text, 'AFP')
    || str_contains($blocks, 'auteur/2005-afp')
        ? 'yes'
        : 'no'
) . "\n";
