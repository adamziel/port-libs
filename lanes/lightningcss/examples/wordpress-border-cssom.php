<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'border: 2px solid var(--wp--preset--color--contrast); border-color: var(--wp--preset--color--primary)';
$block = new DeclarationBlock();

$actual = [
    'border' => $block->getProperty($declarations, 'border'),
    'borderTopColor' => $block->getProperty($declarations, 'border-top-color'),
    'removeTopBorderColor' => $block->removeProperty(
        'border-color: var(--wp--preset--color--contrast) var(--wp--preset--color--accent) currentColor currentColor; padding: var(--wp--preset--spacing--30)',
        'border-top-color'
    ),
];

$expected = [
    'border' => [
        'value' => '2px solid var(--wp--preset--color--primary)',
        'important' => false,
    ],
    'borderTopColor' => [
        'value' => 'var(--wp--preset--color--primary)',
        'important' => false,
    ],
    'removeTopBorderColor' => 'border-right-color: var(--wp--preset--color--accent); border-bottom-color: currentColor; border-left-color: currentColor; padding: var(--wp--preset--spacing--30)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected border CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
