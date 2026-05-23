<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/aclu/source.html');
$url = 'https://www.aclu.org/blog/privacy-technology/internet-privacy/facebook-tracking-me-even-though-im-not-facebook';

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, $url, true);
$blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

echo "Title: {$article->title}\n";
echo 'Site name: ' . ($article->siteName ?? 'null') . "\n";
echo 'Byline: ' . ($article->byline ?? 'null') . "\n";
echo 'Direction: ' . ($article->dir ?? 'null') . "\n";
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
echo 'Image blocks: ' . substr_count($blocks, '<!-- wp:image -->') . "\n";
echo 'Drupal comment/panel chrome retained: ' . (
    str_contains($blocks, 'View comments')
    || str_contains($blocks, 'Read the Terms of Use')
    || str_contains($blocks, 'ACLU Conference')
        ? 'yes'
        : 'no'
) . "\n";
