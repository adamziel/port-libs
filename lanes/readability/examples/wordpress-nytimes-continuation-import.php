<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/nytimes-2/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
$blocks = $extractor->toWordPressBlocks($article);

echo "Title: {$article->title}\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Continuation links: ' . substr_count($article->text, 'Continue reading the main story') . "\n";
echo 'Story ad retained: ' . (str_contains($article->contentHtml, 'story-ad') ? 'yes' : 'no') . "\n";
echo 'Related story rail retained: ' . (str_contains($article->text, 'Justice Department Toughened Approach') ? 'yes' : 'no') . "\n";
