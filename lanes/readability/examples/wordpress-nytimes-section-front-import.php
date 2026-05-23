<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/nytimes-5/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $article->contentHtml . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);

echo "Title: {$article->title}\n";
echo 'Retained media cards: ' . (($xpath->query('//figure')?->length ?? 0)) . "\n";
echo 'Retained story summaries: ' . (($xpath->query('//p')?->length ?? 0)) . "\n";
echo 'Latest stream retained: ' . (str_contains($article->text, 'Elogio de la pereza') ? 'yes' : 'no') . "\n";
echo 'Secondary highlight rail retained: ' . (str_contains($article->text, 'Charles M. Blow') ? 'yes' : 'no') . "\n";
echo 'Tab navigation in blocks: ' . (str_contains($blocks, '#stream-panel') ? 'yes' : 'no') . "\n";
