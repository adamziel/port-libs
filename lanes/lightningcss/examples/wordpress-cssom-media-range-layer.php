<?php

declare(strict_types=1);

use PortLibs\LightningCSS\StylesheetParser;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media screen and (min-width: 48rem), (hover) {
    .wp-site-blocks {
      padding-inline: clamp(1rem, 2vw, 2rem);
    }
  }
}
CSS;

$rules = (new StylesheetParser())->parse($css);
$actual = [
    'layer' => $rules[0]->prelude ?? null,
    'media' => $rules[0]->rules[0]->prelude ?? null,
    'selector' => $rules[0]->rules[0]->rules[0]->selectors[0] ?? null,
    'paddingInline' => $rules[0]->rules[0]->rules[0]->declarations['padding-inline'] ?? null,
];

$expected = [
    'layer' => 'theme.blocks',
    'media' => 'screen and (width>=48rem),(hover)',
    'selector' => '.wp-site-blocks',
    'paddingInline' => 'clamp(1rem, 2vw, 2rem)',
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
