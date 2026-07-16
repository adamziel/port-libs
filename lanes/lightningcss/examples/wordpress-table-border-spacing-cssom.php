<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$tableStyle = 'border-spacing: 0px 0px; border-collapse: separate; color: var(--wp--preset--color--contrast)';

$actual = [
    'spacing' => $block->getProperty($tableStyle, 'border-spacing'),
    'looseSpacing' => $block->setProperty($tableStyle, 'border-spacing', '0px 12px'),
    'compactSpacing' => $block->setProperty(
        'border-collapse: separate',
        'border-spacing',
        '4px 4px'
    ),
    'withoutSpacing' => $block->removeProperty($tableStyle, 'border-spacing'),
];

$expected = [
    'spacing' => [
        'value' => '0',
        'important' => false,
    ],
    'looseSpacing' => 'border-spacing: 0 12px; border-collapse: separate; color: var(--wp--preset--color--contrast)',
    'compactSpacing' => 'border-collapse: separate; border-spacing: 4px',
    'withoutSpacing' => 'border-collapse: separate; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected table border-spacing CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
