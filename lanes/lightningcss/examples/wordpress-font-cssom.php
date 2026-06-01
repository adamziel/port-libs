<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'font: oblique +014.000deg 600 16px/1.5 "Inter", sans-serif; letter-spacing: .01em';

$actual = [
    'font' => $block->getProperty($declarations, 'font'),
    'fontFamily' => $block->getProperty($declarations, 'font-family'),
    'expandedWeight' => $block->setProperty($declarations, 'font-weight', '700'),
    'defaultOblique' => $block->setProperty($declarations, 'font-style', 'Oblique 14deg'),
    'themeFamily' => $block->setProperty($declarations, 'font-family', '"Source Serif 4", serif'),
    'dropLineHeight' => $block->removeProperty($declarations, 'line-height'),
    'dropFont' => $block->removeProperty($declarations, 'font'),
];

$expected = [
    'font' => [
        'value' => 'oblique 600 16px/1.5 Inter, sans-serif',
        'important' => false,
    ],
    'fontFamily' => [
        'value' => 'Inter, sans-serif',
        'important' => false,
    ],
    'expandedWeight' => 'font: oblique 700 16px/1.5 Inter, sans-serif; letter-spacing: .01em',
    'defaultOblique' => 'font: oblique 600 16px/1.5 Inter, sans-serif; letter-spacing: .01em',
    'themeFamily' => 'font: oblique 600 16px/1.5 "Source Serif 4", serif; letter-spacing: .01em',
    'dropLineHeight' => 'font-family: Inter, sans-serif; font-size: 16px; font-style: oblique; font-weight: 600; font-stretch: normal; font-variant-caps: normal; letter-spacing: .01em',
    'dropFont' => 'letter-spacing: .01em',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
