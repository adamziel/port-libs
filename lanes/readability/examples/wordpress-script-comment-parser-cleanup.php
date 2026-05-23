<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/comment-inside-script-parsing/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Script comment payload imported: ' . (
    str_contains($blocks, 'Silly test') || str_contains($blocks, 'foo.js') || str_contains($blocks, '<script')
        ? 'yes'
        : 'no'
) . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
