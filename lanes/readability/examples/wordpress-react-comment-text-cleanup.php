<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Contributor Cleanup">
</head>
<body>
  <article>
    <h1>Contributor Cleanup</h1>
    <p>Legacy WordPress migrations often include hydrated React or Next.js markup copied from the source page.</p>
    <p>Editorial paragraphs should remain intact after parser comments and source wrappers are removed.</p>
    <div class="contributors">Review Desk<!-- --> and <!-- -->Migration Team<!-- --> contributed<!-- -->.</div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
