<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Article.php';
require_once __DIR__ . '/../src/ArticleExtractor.php';

use PortLibs\Readability\ArticleExtractor;

$source = '<html><head><title>Definition List Import</title></head><body><article>'
    . '<h1>Definition List Import</h1>'
    . '<p>' . str_repeat('Long encyclopedia and technical imports can retain definition lists as meaningful article structure. ', 3) . '</p>'
    . '<dl><dt>Reader mode</dt><dd>Clean article content extracted from source chrome.</dd><dt>Migration review</dt><dd>Preserved semantic structure before block editing.</dd></dl>'
    . '<p>' . str_repeat('Follow-up prose should remain ordinary paragraph content after the retained list. ', 3) . '</p>'
    . '</article></body></html>';

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);
$blocks = $extractor->toWordPressBlocks($article);

echo 'Title: ' . $article->title . PHP_EOL;
echo 'Definition list HTML block: ' . (str_contains($blocks, '<!-- wp:html -->') && str_contains($blocks, '<dl>') ? 'yes' : 'no') . PHP_EOL;
echo 'Paragraph-wrapped definition list: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<dl>") ? 'yes' : 'no') . PHP_EOL;
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . PHP_EOL;
