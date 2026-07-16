<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/lemonde-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Language: ' . ($article->lang ?? 'null') . "\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Dailymotion embed retained: ' . (str_contains($blocks, 'www.dailymotion.com/embed/video/x2p552m') ? 'yes' : 'no') . "\n";
echo 'Navigation/ad chrome retained: ' . (
    str_contains($blocks, "S'abonner au Monde")
    || str_contains($blocks, 'OUTBRAIN')
    || str_contains($blocks, 'Suivre @lemondefr')
        ? 'yes'
        : 'no'
) . "\n";
