<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/la-nacion/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Excerpt: ' . $article->excerpt . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Lead description retained: ' . (str_contains($blocks, 'Los pueblos indígenas reclaman') ? 'yes' : 'no') . "\n";
echo 'Leading BOM retained: ' . (str_starts_with($article->text, "\xEF\xBB\xBF") ? 'yes' : 'no') . "\n";
echo 'Navigation chrome retained: ' . (str_contains($blocks, 'MENÚ') || str_contains($blocks, 'NO SOPORTADO') ? 'yes' : 'no') . "\n";
