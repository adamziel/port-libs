<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Paged Medium Import">
</head>
<body>
  <article>
    <div class="postField postField--body">
      <div>
        <p>The first imported page should remain editorial paragraph content without a synthetic separator block.</p>
      </div>
      <hr>
      <div>
        <p>The second imported page should follow as ordinary WordPress paragraph content.</p>
      </div>
    </div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/imports/paged-medium');

echo $article->contentHtml . PHP_EOL . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
