<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Reusable Pattern Migration Planning Guide – Legacy Agency Site</title>
</head>
<body>
  <article>
    <h1>Reusable Pattern Migration Planning Guide</h1>
    <p>Imported posts should not carry the source site name in the WordPress post title.</p>
    <h2>Block Review</h2>
    <p>The cleaned article body remains available for core paragraph and heading blocks.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->title . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
