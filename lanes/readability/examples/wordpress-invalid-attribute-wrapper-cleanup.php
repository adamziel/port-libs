<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/invalid-attributes/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Wrapper retained for oracle review: ' . (str_contains($article->contentHtml, '<div><p>') ? 'yes' : 'no') . "\n";
echo 'Invalid attribute syntax retained: ' . (str_contains($article->contentHtml, '"=""') ? 'yes' : 'no') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
