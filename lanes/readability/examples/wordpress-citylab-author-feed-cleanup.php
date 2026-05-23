<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/citylab-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://www.citylab.com/design/2019/04/neon-signage-20th-century-history/588400/', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'https://www.citylab.com/design/2019/04/neon-signage-20th-century-history/588400/'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Author bio retained: ' . (str_contains($blocks, 'The Midcentury Kitchen') ? 'yes' : 'no') . "\n";
echo 'Author feed chrome retained: ' . (
    str_contains($blocks, '/feeds/author/sarah-archer/')
    || str_contains($blocks, '>Feed<')
        ? 'yes'
        : 'no'
) . "\n";
