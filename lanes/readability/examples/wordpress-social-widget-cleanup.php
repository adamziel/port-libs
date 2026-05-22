<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Social Widget Cleanup">
</head>
<body>
  <article>
    <h1>Social Widget Cleanup</h1>
    <p>Legacy WordPress.com exports can include Jetpack like widgets inside the post body.</p>
    <style>.sharedaddy{display:block}</style>
    <script>alert("wrong")</script>
    <div class="sharedaddy sd-block sd-like" id="like-post-wrapper-10">
      <h3>Like this:</h3>
      <span>Like</span>
      <span>Loading...</span>
    </div>
    <p>The cleanup keeps editorial paragraphs while dropping runtime and social chrome.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
