<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/social-buttons/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Share widget chrome retained: ' . (
    str_contains($blocks, 'Share on Facebook')
    || str_contains($blocks, 'Share on Twitter')
    || str_contains($blocks, 'share-buttons')
        ? 'yes'
        : 'no'
) . "\n";
