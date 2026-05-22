<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$placeholder = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
$inlineSvg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M0 0h24v24H0z'/></svg>";

$source = <<<HTML
<html>
<head>
  <title>Data URI Image Import</title>
</head>
<body>
  <article>
    <h1>Data URI Image Import</h1>
    <p>Legacy WordPress imports may include tiny placeholders next to usable responsive image candidates.</p>
    <p><img src="{$placeholder}" data-srcset="https://cdn.example.test/migration-320.jpg 320w, https://cdn.example.test/migration-800.jpg 800w" alt="Migration screenshot"></p>
    <p>Inline SVG diagrams and real embedded image data should still be preserved when they are the actual article media.</p>
    <p><img src="{$inlineSvg}" alt="Inline migration diagram"></p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
