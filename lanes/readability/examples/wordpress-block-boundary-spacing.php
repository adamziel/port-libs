<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Import Boundary Spacing">
</head>
<body>
  <article>
    <h1>Import Boundary Spacing</h1>
    <p>Version 1.0.</p>
    <h2>Release Plan</h2>
    <p>Legacy exports do not always leave whitespace between adjacent block tags.</p>
    <table>
      <tbody>
        <tr><td>Status complete.</td><td>Next review.</td></tr>
      </tbody>
    </table>
    <p>The native extractor keeps review text readable before block serialization.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->text . PHP_EOL . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
