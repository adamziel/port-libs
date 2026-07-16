<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$layoutGrid = 'grid: auto-flow dense var(--wp--preset--spacing--40) / minmax(0, 1fr); color: var(--wp--preset--color--contrast)';

$actual = [
    'flow' => $block->getProperty($layoutGrid, 'grid-auto-flow'),
    'autoRows' => $block->getProperty($layoutGrid, 'grid-auto-rows'),
    'templateColumns' => $block->getProperty($layoutGrid, 'grid-template-columns'),
    'expandedColumns' => $block->setProperty($layoutGrid, 'grid-template-columns', 'minmax(0, 2fr)'),
    'expandedRowTrack' => $block->setProperty($layoutGrid, 'grid-auto-rows', 'var(--wp--preset--spacing--60)'),
    'withoutAutoFlow' => $block->removeProperty($layoutGrid, 'grid-auto-flow'),
];

$expected = [
    'flow' => [
        'value' => 'row dense',
        'important' => false,
    ],
    'autoRows' => [
        'value' => 'var(--wp--preset--spacing--40)',
        'important' => false,
    ],
    'templateColumns' => [
        'value' => 'minmax(0, 1fr)',
        'important' => false,
    ],
    'expandedColumns' => 'grid: auto-flow dense var(--wp--preset--spacing--40) / minmax(0, 2fr); color: var(--wp--preset--color--contrast)',
    'expandedRowTrack' => 'grid: auto-flow dense var(--wp--preset--spacing--60) / minmax(0, 1fr); color: var(--wp--preset--color--contrast)',
    'withoutAutoFlow' => 'grid-template-rows: none; grid-template-columns: minmax(0, 1fr); grid-template-areas: none; grid-auto-rows: var(--wp--preset--spacing--40); grid-auto-columns: auto; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected grid auto-flow CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
