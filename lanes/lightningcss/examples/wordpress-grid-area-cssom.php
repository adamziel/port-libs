<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'grid-area: content-start / sidebar-start / content-end / sidebar-end';
$block = new DeclarationBlock();

$actual = [
    'gridRowStart' => $block->getProperty($declarations, 'grid-row-start'),
    'gridRow' => $block->getProperty($declarations, 'grid-row'),
    'gridColumn' => $block->getProperty($declarations, 'grid-column'),
    'movedBlockArea' => $block->setProperty($declarations, 'grid-column-end', 'wide-end'),
    'removedRowStart' => $block->removeProperty($declarations, 'grid-row-start'),
];

$expected = [
    'gridRowStart' => [
        'value' => 'content-start',
        'important' => false,
    ],
    'gridRow' => [
        'value' => 'content-start / content-end',
        'important' => false,
    ],
    'gridColumn' => [
        'value' => 'sidebar-start / sidebar-end',
        'important' => false,
    ],
    'movedBlockArea' => 'grid-area: content-start / sidebar-start / content-end / wide-end',
    'removedRowStart' => 'grid-column-start: sidebar-start; grid-row-end: content-end; grid-column-end: sidebar-end',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected grid area CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
