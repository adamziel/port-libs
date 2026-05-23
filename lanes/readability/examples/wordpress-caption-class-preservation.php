<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Caption Class Import">
</head>
<body>
  <article>
    <h1>Caption Class Import</h1>
    <p>Some WordPress migration pipelines need caption classes for media review while still dropping source theme classes.</p>
    <figure class="wp-caption aligncenter theme-frame">
      <img src="/uploads/captioned.jpg" alt="Captioned import">
      <figcaption class="wp-caption-text legacy-caption">Imported media caption</figcaption>
    </figure>
    <p>The extractor keeps only the requested WordPress caption contract.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, null, false, ['wp-caption', 'aligncenter', 'wp-caption-text']);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
