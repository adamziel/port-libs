<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = '<html><head><title>Migration Checklist</title></head><body><article>'
    . '<h1>Migration Checklist</h1>'
    . '<p>Short editorial checklists should remain reviewable as native WordPress list blocks during migration.</p>'
    . '<ul data-wp-block-list="1"><li><p>Keep the source permalink for reviewer traceability.</p></li><li><p>Keep the media sideload note near the imported copy.</p></li></ul>'
    . '<ol><li><p>Confirm imported excerpts and block counts before publishing.</p></li></ol>'
    . '</article></body></html>';

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Unordered list blocks: ' . substr_count($blocks, '<!-- wp:list -->') . "\n";
echo 'Ordered list blocks: ' . substr_count($blocks, '<!-- wp:list {"ordered":true} -->') . "\n";
echo 'Paragraph-wrapped ordered lists: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<ol>") ? 'yes' : 'no') . "\n";
echo 'Paragraph-wrapped unordered lists: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<ul") ? 'yes' : 'no') . "\n";
