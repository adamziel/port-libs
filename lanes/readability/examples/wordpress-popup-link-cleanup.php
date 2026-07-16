<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Legacy Popup Link Cleanup">
</head>
<body>
  <article>
    <h1>Legacy Popup Link Cleanup</h1>
    <p>Older WordPress exports can include popup note anchors and whitespace-padded media attributes.</p>
    <p><a href="javascript:void(0); " onclick="return showNote('Theme note')">Theme note</a> should become plain article text.</p>
    <figure>
      <img src="/wp-content/uploads/migration-note.jpg " alt="Migration note">
    </figure>
    <p><a href="related/export-map.html ">Export map</a> remains a clean absolute editorial link.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/imports/post.html');

echo $article->title . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
