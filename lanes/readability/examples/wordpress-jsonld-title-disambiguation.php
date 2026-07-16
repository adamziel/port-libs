<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Canonical Import Title – Legacy Site</title>
  <script type="application/ld+json">
  {
    "@context": "http://schema.org",
    "@type": "NewsArticle",
    "name": "Canonical Import Title",
    "headline": "Injected Theme Teaser Should Not Win",
    "description": "Structured excerpt for the import.",
    "author": {"@type": "Person", "name": "Migration Desk"},
    "publisher": {"@type": "Organization", "name": "Legacy Site"},
    "datePublished": "2024-05-01T10:00:00+00:00"
  }
  </script>
</head>
<body>
  <article>
    <h1>Canonical Import Title</h1>
    <p>Legacy WordPress migrations can carry plugin-injected structured data with competing title-like fields.</p>
    <p>The native extractor keeps the title that matches the source document and imported post metadata.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->title . PHP_EOL;
echo $article->byline . PHP_EOL;
echo $article->excerpt . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
