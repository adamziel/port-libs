<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Visible Date Boundary">
</head>
<body>
  <article>
    <header>
      <h1>Visible Date Boundary</h1>
      <p><time datetime="2024-04-09T12:00:00+00:00" itemprop="datePublished">April 9, 2024</time></p>
    </header>
    <div itemprop="articleBody">
      <p>A WordPress importer should not promote visible template dates to post metadata unless upstream Readability metadata fields support it.</p>
      <p>The editorial body remains available for block output while the import layer decides whether to trust source template dates.</p>
    </div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->title . PHP_EOL;
echo var_export($article->publishedTime, true) . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
