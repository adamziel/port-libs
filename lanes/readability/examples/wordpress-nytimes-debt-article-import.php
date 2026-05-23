<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/nytimes-4/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Debt chart chrome retained: ' . (str_contains($article->text, 'Annual interest payments on the national debt') ? 'yes' : 'no') . "\n";
echo 'Related link cards retained: ' . (str_contains($article->text, 'Trump Administration Mulls a Unilateral Tax Cut') ? 'yes' : 'no') . "\n";
echo 'Share tools retained: ' . (str_contains($article->contentHtml, 'data-testid="share-tools"') ? 'yes' : 'no') . "\n";
