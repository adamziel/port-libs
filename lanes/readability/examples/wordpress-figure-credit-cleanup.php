<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Credit Caption Import">
</head>
<body>
  <article>
    <h1>Credit Caption Import</h1>
    <p>A WordPress import should keep editorial media while dropping source-only photo credit links from captions.</p>
    <figure class="wp-caption source-frame">
      <img src="/uploads/server-crash.jpg" alt="Imported media">
      <figcaption class="wp-caption-text">
        <div class="caption-credit">
          <a class="caption-link" href="https://source.example/credit">Source Photographer</a>
        </div>
      </figcaption>
    </figure>
    <p>The resulting blocks stay focused on portable article copy and reviewable media.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, null, false, ['wp-caption', 'wp-caption-text']);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
