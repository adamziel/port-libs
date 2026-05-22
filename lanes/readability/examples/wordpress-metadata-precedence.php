<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Theme Fallback Title</title>
  <meta property="x:title dc:title" content="Canonical Import Title">
  <meta property="dc:creator twitter:site_name" content="Migration Desk">
  <meta name="author" content="Wrong Theme Author">
  <meta property="og:description twitter:description">
  <meta property="dc:description" content="Clean import excerpt from migrated metadata.">
</head>
<body>
  <article>
    <h1>Visible Article Heading</h1>
    <p>Portable metadata should win over incomplete theme tags during a WordPress import.</p>
    <p>The editorial body remains available for clean block serialization.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->title . PHP_EOL;
echo $article->byline . PHP_EOL;
echo $article->excerpt . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
