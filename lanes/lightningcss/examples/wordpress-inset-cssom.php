<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'inset: 0; inset-inline-start: var(--wp--preset--spacing--40)';

$actual = [
    'overlayInset' => $block->getProperty($declarations, 'inset'),
    'topOverride' => $block->setProperty($declarations, 'top', 'var(--wp--preset--spacing--20)'),
    'logicalOverride' => $block->setProperty(
        'top: 0; inset-inline-start: var(--wp--preset--spacing--30)',
        'inset-inline-start',
        'var(--wp--preset--spacing--50)'
    ),
    'removedPhysicalInset' => $block->removeProperty(
        'inset: 0; inset-inline-start: var(--wp--preset--spacing--40); pointer-events: none',
        'inset'
    ),
];

$expected = [
    'overlayInset' => [
        'value' => '0',
        'important' => false,
    ],
    'topOverride' => 'inset: 0; inset-inline-start: var(--wp--preset--spacing--40); top: var(--wp--preset--spacing--20)',
    'logicalOverride' => 'top: 0; inset-inline-start: var(--wp--preset--spacing--50)',
    'removedPhysicalInset' => 'inset-inline-start: var(--wp--preset--spacing--40); pointer-events: none',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected inset CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
