<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Section Wrapper Cleanup">
</head>
<body>
  <article>
    <h1>Section Wrapper Cleanup</h1>
    <section class="wp-block-group alignwide">
      <div class="wp-block-group__inner-container">
        <p>Legacy page builders often wrap migrated article copy in section shells that only carry layout classes.</p>
        <p>The native extractor should keep the editorial paragraphs while dropping transparent section wrappers.</p>
      </div>
    </section>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
