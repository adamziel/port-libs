<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Classic Editor Font Cleanup</title>
</head>
<body>
  <article>
    <h1>Classic Editor Font Cleanup</h1>
    <p><font face="Georgia" size="4">Classic editor exports can preserve editorial emphasis</font> without keeping obsolete font elements.</p>
    <p>The importer keeps the article copy and reviewer-visible attributes while preventing legacy font tags from entering block output.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
