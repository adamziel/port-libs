<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Metadata Entity Cleanup</title>
  <meta name="description" content="Migrated excerpt keeps emoji &amp;#128557; and replaces invalid source entities &amp;#x0; before import.">
</head>
<body>
  <article>
    <h1>Metadata Entity Cleanup</h1>
    <p>Legacy WordPress migrations sometimes carry double-escaped metadata from old templates or feeds.</p>
    <p>The native cleanup should keep excerpts parser-safe before storing them as post metadata.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->excerpt . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
