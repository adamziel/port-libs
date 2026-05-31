<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'outline: 2px solid var(--wp--preset--color--contrast); outline-offset: 2px';

$actual = [
    'focusColor' => $block->getProperty($declarations, 'outline-color'),
    'accentRing' => $block->setProperty($declarations, 'outline-color', 'var(--wp--preset--color--accent)'),
    'autoStyle' => $block->setProperty($declarations, 'outline-style', 'auto'),
    'colorRemoved' => $block->removeProperty($declarations, 'outline-color'),
    'ringRemoved' => $block->removeProperty($declarations, 'outline'),
];

$expected = [
    'focusColor' => [
        'value' => 'var(--wp--preset--color--contrast)',
        'important' => false,
    ],
    'accentRing' => 'outline: 2px solid var(--wp--preset--color--accent); outline-offset: 2px',
    'autoStyle' => 'outline: 2px auto var(--wp--preset--color--contrast); outline-offset: 2px',
    'colorRemoved' => 'outline-width: 2px; outline-style: solid; outline-offset: 2px',
    'ringRemoved' => 'outline-offset: 2px',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected outline CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
