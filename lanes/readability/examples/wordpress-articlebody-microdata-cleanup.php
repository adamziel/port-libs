<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$body = str_repeat('Portable WordPress articleBody copy should win over template chrome. ', 4);
$source = <<<HTML
<html>
<head>
  <title>Microdata Import</title>
</head>
<body>
  <article itemprop="blogPost">
    <h1>Microdata Import</h1>
    <div itemprop="articleBody">
      <p>{$body}</p>
      <p>{$body}</p>
    </div>
    <div id="terms">
      <p>Tagged migration import block editor review queue sidebar note should not become block content.</p>
    </div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
