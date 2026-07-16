<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/dev418/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Images retained: ' . substr_count($article->contentHtml, '<img') . "\n";
echo 'Figures retained: ' . substr_count($article->contentHtml, '<figure') . "\n";
echo 'Separator blocks: ' . substr_count($blocks, '<!-- wp:separator -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'List blocks: ' . substr_count($blocks, '<!-- wp:list') . "\n";
echo 'Image list wrapped as paragraph: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<ul>") ? 'yes' : 'no') . "\n";
