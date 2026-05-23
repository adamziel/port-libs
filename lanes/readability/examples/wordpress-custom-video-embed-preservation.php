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

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
