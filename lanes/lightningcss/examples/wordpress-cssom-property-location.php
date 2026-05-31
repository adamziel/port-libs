<?php

declare(strict_types=1);

use PortLibs\LightningCSS\StylesheetParser;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverColor = 'var(--wp--preset--color--contrast)';
$coverBackground = 'linear-gradient(red, blue)';
$css = <<<CSS
.wp-block-cover {
  color: {$coverColor};
  background: {$coverBackground};
}

@media print {
  .wp-block-cover {
    color: black;
  }
}
CSS;

$parser = new StylesheetParser();
$actual = [
    'coverColor' => $parser->propertyLocation($css, [0], 0),
    'coverBackground' => $parser->propertyLocation($css, [0], 1),
    'printColor' => $parser->propertyLocation($css, [1, 0], 0),
];
$expected = [
    'coverColor' => [
        'key' => ['start' => ['line' => 2, 'column' => 3], 'end' => ['line' => 2, 'column' => 8]],
        'value' => ['start' => ['line' => 2, 'column' => 10], 'end' => ['line' => 2, 'column' => 10 + strlen($coverColor)]],
    ],
    'coverBackground' => [
        'key' => ['start' => ['line' => 3, 'column' => 3], 'end' => ['line' => 3, 'column' => 13]],
        'value' => ['start' => ['line' => 3, 'column' => 15], 'end' => ['line' => 3, 'column' => 15 + strlen($coverBackground)]],
    ],
    'printColor' => [
        'key' => ['start' => ['line' => 8, 'column' => 5], 'end' => ['line' => 8, 'column' => 10]],
        'value' => ['start' => ['line' => 8, 'column' => 12], 'end' => ['line' => 8, 'column' => 17]],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSSOM property location output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
