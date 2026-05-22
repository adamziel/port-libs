<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Plugin Compatibility Matrix">
</head>
<body>
  <article>
    <h1>Plugin Compatibility Matrix</h1>
    <p>Migration notes can include compact compatibility tables from legacy docs.</p>
    <table summary="Plugin compatibility matrix">
      <tbody>
        <tr>
          <td>Import Helper works with <a href="/plugins/import-helper">current exports</a>.</td>
        </tr>
      </tbody>
    </table>
    <table role="presentation">
      <tbody>
        <tr>
          <td>This layout-only wrapper becomes ordinary article copy.</td>
        </tr>
      </tbody>
    </table>
    <p>The real table becomes a core table block; the layout table does not.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/import/post.html');

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
