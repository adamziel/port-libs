<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Readability Wrapper Import">
</head>
<body>
  <article>
    <h1>Readability Wrapper Import</h1>
    <div>
      <div>
        <div>
          <p><a href="/authors/editor"><img src="/uploads/editor-avatar.jpg" alt="Editor avatar"></a></p>
        </div>
        <div></div>
      </div>
    </div>
    <p>Migration tools may preserve Mozilla's readability-page wrapper for fixture comparison.</p>
    <p>WordPress block output should still flatten source wrappers and keep editorial paragraphs.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/imports/post.html', true);

echo $article->contentHtml . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
