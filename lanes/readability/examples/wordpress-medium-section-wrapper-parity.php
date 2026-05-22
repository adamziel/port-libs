<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Medium Section Import">
</head>
<body>
  <article>
    <div class="postField postField--body">
      <section name="wpsec" class="section--first section--last">
        <div class="section-content">
          <div>
            <p>Migration reviewers may need source section boundaries when comparing against Mozilla Readability fixture output.</p>
          </div>
          <div>
            <figure class="postField--fillWidthImage">
              <div><img src="/uploads/section-photo.jpg" alt="Section photo"></div>
              <figcaption>Section photo</figcaption>
            </figure>
          </div>
          <div>
            <p>WordPress block output should still flatten opaque Medium section shells.</p>
          </div>
        </div>
      </section>
    </div>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$oracleArticle = $extractor->extract($source, 'https://example.com/imports/post.html', true);
$wordpressArticle = $extractor->extract($source, 'https://example.com/imports/post.html');

echo $oracleArticle->contentHtml . PHP_EOL;
echo $extractor->toWordPressBlocks($wordpressArticle) . PHP_EOL;
