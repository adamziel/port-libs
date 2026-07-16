<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/lifehacker-post-comment-load/source.html');
$url = 'http://lifehacker.com/how-to-program-your-mind-to-stop-buying-crap-you-don-t-1690268064';

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, $url, true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

echo "Title: {$article->title}\n";
echo "Byline: {$article->byline}\n";
echo "Site: {$article->siteName}\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'List blocks: ' . substr_count($blocks, '<!-- wp:list') . "\n";
echo 'Kinja chrome retained: ' . (
    str_contains($blocks, 'Show more comments')
    || str_contains($blocks, 'Ads by Google')
    || str_contains($blocks, 'Follow Lifehacker')
        ? 'yes'
        : 'no'
) . "\n";
echo 'Lists wrapped as paragraphs: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<ul>") ? 'yes' : 'no') . "\n";
echo 'Source annotation ids retained in article HTML: ' . (str_contains($article->contentHtml, 'data-textannotation-id=') ? 'yes' : 'no') . "\n";
echo 'Source annotation ids retained in blocks: ' . (str_contains($blocks, 'data-textannotation-id=') ? 'yes' : 'no') . "\n";
