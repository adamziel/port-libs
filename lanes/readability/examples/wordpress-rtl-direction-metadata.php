<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html dir="ltr">
<head>
  <title>RTL Import Direction</title>
</head>
<body dir="rtl">
  <main>
    <article>
      <h1>RTL Import Direction</h1>
      <p>A WordPress migration should keep article direction metadata from the source wrapper.</p>
      <p>The cleaned block content can then be imported while the post or wrapper receives dir="rtl".</p>
    </article>
  </main>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo 'dir=' . ($article->dir ?? 'none') . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
