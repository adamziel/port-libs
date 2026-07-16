<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Footnote Wrapper Cleanup">
</head>
<body>
  <article>
    <h1>Footnote Wrapper Cleanup</h1>
    <p>Legacy WordPress themes sometimes wrap citation text in layout divs.</p>
    <div id="footnote-shell">
      <p>Keep the paragraph and its <a href="#citation-one">long internal citation reference</a>, but remove the wrapper.</p>
    </div>
    <p id="citation-one">The local citation target remains available after block serialization.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
