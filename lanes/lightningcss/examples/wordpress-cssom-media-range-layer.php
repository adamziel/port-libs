<?php

declare(strict_types=1);

use PortLibs\LightningCSS\StylesheetParser;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @layer reset;
  @media screen and (min-width: 48rem), (hover) {
    .wp-site-blocks {
      padding-inline: clamp(1rem, 2vw, 2rem);
    }
  }
}
CSS;

$rules = (new StylesheetParser())->parse($css);
$location = (new StylesheetParser())->propertyLocation($css, [0, 1, 0], 0);
$actual = [
    'layer' => $rules[0]->prelude ?? null,
    'statement' => $rules[0]->rules[0]->prelude ?? null,
    'media' => $rules[0]->rules[1]->prelude ?? null,
    'selector' => $rules[0]->rules[1]->rules[0]->selectors[0] ?? null,
    'paddingInline' => $rules[0]->rules[1]->rules[0]->declarations['padding-inline'] ?? null,
    'paddingLocation' => $location,
];

$expected = [
    'layer' => 'theme.blocks',
    'statement' => 'reset',
    'media' => 'screen and (width>=48rem),(hover)',
    'selector' => '.wp-site-blocks',
    'paddingInline' => 'clamp(1rem, 2vw, 2rem)',
    'paddingLocation' => [
        'key' => ['start' => ['line' => 5, 'column' => 7], 'end' => ['line' => 5, 'column' => 21]],
        'value' => ['start' => ['line' => 5, 'column' => 23], 'end' => ['line' => 5, 'column' => 45]],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSSOM media range layer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
