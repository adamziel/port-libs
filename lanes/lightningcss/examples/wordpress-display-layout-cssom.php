<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'display: Inline Flow-Root; visibility: Collapse; position: Sticky; box-sizing: Border-Box; transform-box: Fill-Box; vertical-align: SUPER; perspective: 0px; --wp--custom--display: Inline Flow-Root';

$actual = [
    'canonicalDisplay' => $block->getProperty($declarations, 'display'),
    'layoutState' => $block->parse($declarations),
    'themeOverride' => $block->setProperty($declarations, 'display', 'inline flex'),
    'hiddenVariant' => $block->setProperty($declarations, 'visibility', 'Hidden', true),
];

$expected = [
    'canonicalDisplay' => ['value' => 'inline-block', 'important' => false],
    'layoutState' => [
        'display' => 'inline-block',
        'visibility' => 'collapse',
        'position' => 'sticky',
        'box-sizing' => 'border-box',
        'transform-box' => 'fill-box',
        'vertical-align' => 'super',
        'perspective' => '0',
        '--wp--custom--display' => 'Inline Flow-Root',
    ],
    'themeOverride' => 'display: inline-flex; visibility: collapse; position: sticky; box-sizing: border-box; transform-box: fill-box; vertical-align: super; perspective: 0; --wp--custom--display: Inline Flow-Root',
    'hiddenVariant' => 'display: inline-block; position: sticky; box-sizing: border-box; transform-box: fill-box; vertical-align: super; perspective: 0; --wp--custom--display: Inline Flow-Root; visibility: hidden !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected display layout CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
