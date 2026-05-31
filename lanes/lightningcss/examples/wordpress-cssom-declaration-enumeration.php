<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'color: var(--wp--preset--color--accent) !important; background: var(--wp--preset--color--base); --Block-Accent: var(--wp--preset--color--contrast); margin-block-start: var(--wp--preset--spacing--40) !important; color: var(--wp--preset--color--primary)';

$actual = [
    'length' => $block->length($declarations),
    'items' => [
        $block->item($declarations, 0),
        $block->item($declarations, 1),
        $block->item($declarations, 2),
        $block->item($declarations, 3),
        $block->item($declarations, 4),
        $block->item($declarations, 5),
    ],
    'activeColor' => $block->getProperty($declarations, 'color'),
    'withoutImportantColor' => $block->removeProperty($declarations, 'color'),
];

$expected = [
    'length' => 5,
    'items' => [
        'background',
        '--Block-Accent',
        'color',
        'color',
        'margin-block-start',
        null,
    ],
    'activeColor' => ['value' => 'var(--wp--preset--color--accent)', 'important' => true],
    'withoutImportantColor' => 'background: var(--wp--preset--color--base); --Block-Accent: var(--wp--preset--color--contrast); margin-block-start: var(--wp--preset--spacing--40) !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected declaration enumeration CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
