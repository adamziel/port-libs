<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/simplyfound-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Language: ' . ($article->lang ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Account approval modal retained: ' . (str_contains($blocks, 'approval/request') || str_contains($article->text, 'approved author') ? 'yes' : 'no') . "\n";
echo 'Trailing ad retained: ' . (str_contains($article->contentHtml, 'adsbygoogle') ? 'yes' : 'no') . "\n";
