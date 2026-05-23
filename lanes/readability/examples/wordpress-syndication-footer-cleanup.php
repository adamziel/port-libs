<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Syndicated Review">
</head>
<body>
  <article>
    <div class="entry-content">
      <p>The importer should retain the syndicated article body while dropping source-platform footer notes.</p>
      <p>This keeps the migrated post focused on editorial content instead of adding stale original-source notes as paragraph blocks.</p>
      <section class="medium-source-note">
        <p><em>Originally published at <a href="https://old.example/post">old.example</a> on November 18, 2011. Help the word out. Recommend this article to your readers.</em></p>
      </section>
    </div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/imported/syndicated-review');

echo $article->text . PHP_EOL . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
