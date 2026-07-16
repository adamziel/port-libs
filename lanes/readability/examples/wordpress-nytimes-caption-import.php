<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/nytimes-1/source.html');

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
$blocks = $extractor->toWordPressBlocks($article);

$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $article->contentHtml . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($previous);

$xpath = new DOMXPath($dom);
$caption = trim(preg_replace('/\s+/', ' ', $xpath->query('//figcaption')?->item(0)?->textContent ?? '') ?? '');

echo "Title: {$article->title}\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo "Caption: {$caption}\n";
echo 'Feedback prompt retained: ' . (str_contains($article->text, 'We’re interested in your feedback') ? 'yes' : 'no') . "\n";
