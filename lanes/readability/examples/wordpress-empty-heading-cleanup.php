<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Empty Heading Import">
</head>
<body>
  <article>
    <h1>Empty Heading Import</h1>
    <h4><br></h4>
    <p>Visual spacer headings from source editors should not become empty WordPress heading blocks.</p>
    <h2>Review Notes</h2>
    <h3> </h3>
    <p>The native cleanup keeps text boundaries readable around real headings and paragraphs.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->text . PHP_EOL . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
