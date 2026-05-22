<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Inline Source Junk">
</head>
<body>
  <article>
    <h1>Inline Source Junk</h1>
    <p>Some WordPress exports include theme stylesheet links and form controls inside the article body.</p>
    <link rel="stylesheet" href="/wp-content/themes/old-theme/editor.css">
    <fieldset>
      <legend>Subscribe before import</legend>
      <input name="email" value="reader@example.com">
    </fieldset>
    <p>The native cleanup keeps editorial copy while stripping those non-content fragments before block output.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
