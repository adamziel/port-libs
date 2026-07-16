<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Custom Video Import - Legacy Publisher</title>
</head>
<body>
  <article class="legacy-entry content-shell">
    <h1>Custom Video Import</h1>
    <p>Some WordPress migrations need to preserve a trusted legacy oEmbed provider that is not in Mozilla Readability's default YouTube and Vimeo video allowlist.</p>
    <iframe class="legacy-oembed" src="https://video.example.test/embed/123"></iframe>
    <iframe src="https://widgets.example.test/ad"></iframe>
    <p>The trusted video iframe stays available for manual block conversion, while unrelated widgets are removed before import.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extractWithOptions($source, [
    'allowedVideoRegex' => '~//video\.example\.test/embed/~',
    'keepClasses' => true,
]);

$blocks = $extractor->toWordPressBlocks($article);

echo 'HTML embed blocks: ' . substr_count($blocks, '<!-- wp:html -->') . "\n";
echo 'Trusted video retained: ' . (str_contains($blocks, 'https://video.example.test/embed/123') ? 'yes' : 'no') . "\n";
echo 'Widget iframe removed: ' . (str_contains($blocks, 'widgets.example.test') ? 'no' : 'yes') . "\n";
echo 'Paragraph-wrapped iframes: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<iframe") ? 'yes' : 'no') . "\n";
