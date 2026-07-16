<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'color: InHeRiT; margin: REVERT-LAYER; border-spacing: ReVeRt; --Block-Reset: InHeRiT';

$actual = [
    'themeColor' => $block->getProperty($declarations, 'color'),
    'spacingReset' => $block->getProperty($declarations, 'margin-top'),
    'tableSpacingReset' => $block->getProperty($declarations, 'border-spacing'),
    'customTokenPreserved' => $block->getProperty($declarations, '--Block-Reset'),
    'editorColorReset' => $block->setProperty($declarations, 'color', 'ReVeRt-LaYeR'),
    'editorSpacingReset' => $block->setProperty($declarations, 'margin', 'UNSET', true),
];

$expected = [
    'themeColor' => ['value' => 'inherit', 'important' => false],
    'spacingReset' => ['value' => 'revert-layer', 'important' => false],
    'tableSpacingReset' => ['value' => 'revert', 'important' => false],
    'customTokenPreserved' => ['value' => 'InHeRiT', 'important' => false],
    'editorColorReset' => 'color: revert-layer; margin: revert-layer; border-spacing: revert; --Block-Reset: InHeRiT',
    'editorSpacingReset' => 'color: inherit; border-spacing: revert; --Block-Reset: InHeRiT; margin: unset !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS-wide keyword CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
