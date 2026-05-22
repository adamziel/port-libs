<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Line Break Migration">
</head>
<body>
  <article>
    <h1>Line Break Migration</h1>
    <p>Legacy exports sometimes store paragraph boundaries as repeated line breaks.<br><br>
    The native Readability cleanup turns hard break chains into paragraph boundaries while keeping a single soft<br>
    break inside editorial copy.<br><br>
    The WordPress block serializer can then emit separate paragraph blocks.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
