<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Classic NBSP Export</title>
</head>
<body>&nbsp;&nbsp;
  <table>
    <tbody>
      <tr>
        <td>
          <h1>Classic NBSP Export</h1>
          <p>Classic WordPress exports sometimes leave nonbreaking layout padding before a table-wrapped article.</p>
          <p>The native extractor removes that boundary padding while preserving internal&nbsp;editorial spacing.</p>
        </td>
      </tr>
    </tbody>
  </table>&nbsp;
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source);

echo $article->text . PHP_EOL . PHP_EOL;
echo $extractor->toWordPressBlocks($article) . PHP_EOL;
