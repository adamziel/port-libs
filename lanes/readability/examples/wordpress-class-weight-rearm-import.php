<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Class Weight Rearm Import</title>
</head>
<body>
  <div class="storytext">
    <p>Short teaser copy.</p>
  </div>
  <article class="comment">
    <h1>Class Weight Rearm Import</h1>
    <p>The legacy theme labels the real migrated article as comments even though it contains the complete editorial body for review.</p>
    <p>A threshold rearm disables class weighting after stricter attempts and recovers the longer article for WordPress block serialization.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extractWithOptions($source, [
    'charThreshold' => 1000,
]);

if ($article === null) {
    echo "No importable article found." . PHP_EOL;
    exit(0);
}

$blocks = $extractor->toWordPressBlocks($article);

echo 'Title: ' . $article->title . PHP_EOL;
echo 'Recovered body: ' . (str_contains($blocks, 'complete editorial body') ? 'yes' : 'no') . PHP_EOL;
echo 'Teaser retained: ' . (str_contains($blocks, 'Short teaser copy') ? 'yes' : 'no') . PHP_EOL;
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . PHP_EOL;
