<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <base href="/legacy/imports/">
  <meta property="og:title" content="Migrated URL Cleanup">
</head>
<body>
  <article>
    <h1>Migrated URL Cleanup</h1>
    <p>Legacy pages often store article links and media as relative URLs.</p>
    <p><a href="docs/source-map.html">Source map notes</a></p>
    <figure>
      <img src="media/hero.jpg" srcset="media/hero-320.jpg 320w, /uploads/hero-800.jpg 800w" alt="Imported hero">
    </figure>
    <p>Absolute URLs make WordPress import previews and media sideloading deterministic.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/source/page.html');

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
