<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Single Article Import">
</head>
<body>
  <header>
    <ul><li><a href="/news">News</a></li><li><a href="/forums">Forums</a></li></ul>
  </header>
  <div id="legacy-shell">
    <aside>
      <p>Related stories, subscription links, and site chrome from the source theme.</p>
    </aside>
    <article>
      <p class="post-date">23.05.2026 09:45</p>
      <h1>Single Article Import</h1>
      <div class="entry-content">
        <p>Legacy WordPress exports can bury one real article inside a page shell with much more navigation text than editorial copy.</p>
        <p>The extractor promotes the single substantial article instead of serializing the surrounding shell.</p>
        <p>That keeps block output focused on portable post content for review and migration.</p>
      </div>
    </article>
  </div>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
