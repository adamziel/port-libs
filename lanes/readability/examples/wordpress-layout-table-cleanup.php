<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <meta property="og:title" content="Legacy Table Cleanup">
</head>
<body>
  <article>
    <h1>Legacy Table Cleanup</h1>
    <p>Old WordPress themes and exported page-builder content often use tables as layout wrappers.</p>
    <table class="layout-shell">
      <tbody>
        <tr>
          <td class="layout-cell">
            This paragraph should migrate as article copy, not as a layout table.
            <a href="/docs/table-cleanup">Source notes</a>
          </td>
        </tr>
      </tbody>
    </table>
    <p>Actual multi-cell data tables remain in the extracted content for later table-block handling.</p>
    <table id="migration-checklist">
      <tbody>
        <tr><td>Source</td><td>Status</td></tr>
        <tr><td>Layout wrapper</td><td>Removed</td></tr>
      </tbody>
    </table>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.com/imports/post.html');

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
