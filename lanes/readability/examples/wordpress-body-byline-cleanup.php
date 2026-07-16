<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Body Byline Import</title>
</head>
<body>
  <article>
    <h1>Body Byline Import</h1>
    <p>Some legacy WordPress themes put schema.org author markup inside the post body.</p>
    <p itemprop="author" itemscope itemtype="https://schema.org/Person">
      By <span itemprop="name">Sarah Gooding</span>
    </p>
    <p>The importer should keep the author as post metadata while leaving only editorial copy for block output.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->byline . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
