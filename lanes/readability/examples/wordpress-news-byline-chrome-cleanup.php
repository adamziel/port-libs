<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Newsroom Archive Import</title>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "NewsArticle",
    "headline": "Newsroom Archive Import",
    "description": "Structured excerpt for a legacy news archive migration.",
    "author": [{"@type": "Person", "name": "Migration Desk"}],
    "publisher": {"@type": "Organization", "name": "Legacy News"},
    "datePublished": "2024-02-03T10:00:00+00:00"
  }
  </script>
</head>
<body>
  <main>
    <section>
      <p><time datetime="2024-02-03T10:00:00+00:00">Feb. 3, 2024, 10:00 AM UTC</time></p>
      <div data-activity-map="inline-byline-article-top">By <span data-testid="byline-name">Migration Desk</span></div>
    </section>
    <div class="article-body__content">
      <p>The importer should start block content at the first editorial paragraph, even when a legacy news template has no heading inside the selected article body.</p>
      <p>Timestamp and inline byline wrappers remain available as metadata, but they should not be serialized into WordPress paragraph blocks.</p>
    </div>
  </main>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->title . PHP_EOL;
echo $article->byline . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
