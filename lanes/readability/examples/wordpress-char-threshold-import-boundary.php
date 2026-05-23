<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Threshold Retry Import - Legacy Theme</title>
</head>
<body>
  <div class="comment">
    <h1>Threshold Retry Import</h1>
    <p>Legacy WordPress themes sometimes wrap real editorial copy in containers whose class names look like comment chrome during the first strict Readability pass.</p>
    <p>The threshold retry keeps a short but nonempty article candidate instead of dropping useful content during migration review.</p>
  </div>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extractWithOptions($source, [
    'charThreshold' => 1000,
]);

if ($article === null) {
    echo "No importable article found." . PHP_EOL;
    exit(0);
}

echo $extractor->toWordPressBlocks($article) . PHP_EOL;
