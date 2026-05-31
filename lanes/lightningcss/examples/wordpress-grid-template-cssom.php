<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$longhandDeclarations = 'grid-template-areas: "header header" "content sidebar"; grid-template-rows: [header-start] auto [header-end content-start] 1fr [content-end]; grid-template-columns: minmax(0, 1fr) 18rem; grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto';
$heroTemplate = 'grid-template: auto / minmax(0, 1fr) 18rem; gap: var(--wp--preset--spacing--40)';

$actual = [
    'gridTemplate' => $block->getProperty($longhandDeclarations, 'grid-template'),
    'grid' => $block->getProperty($longhandDeclarations, 'grid'),
    'templateRows' => $block->getProperty($heroTemplate, 'grid-template-rows'),
    'templateColumns' => $block->getProperty($heroTemplate, 'grid-template-columns'),
    'expandedSidebar' => $block->setProperty($heroTemplate, 'grid-template-columns', 'minmax(0, 1fr) 22rem'),
    'resetRows' => $block->removeProperty($heroTemplate, 'grid-template-rows'),
];

$expected = [
    'gridTemplate' => [
        'value' => '[header-start] "header header" [header-end] [content-start] "content sidebar" 1fr [content-end] / minmax(0, 1fr) 18rem',
        'important' => false,
    ],
    'grid' => [
        'value' => '[header-start] "header header" [header-end] [content-start] "content sidebar" 1fr [content-end] / minmax(0, 1fr) 18rem',
        'important' => false,
    ],
    'templateRows' => [
        'value' => 'auto',
        'important' => false,
    ],
    'templateColumns' => [
        'value' => 'minmax(0, 1fr) 18rem',
        'important' => false,
    ],
    'expandedSidebar' => 'grid-template: auto / minmax(0, 1fr) 22rem; gap: var(--wp--preset--spacing--40)',
    'resetRows' => 'grid-template-columns: minmax(0, 1fr) 18rem; grid-template-areas: none; gap: var(--wp--preset--spacing--40)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected grid-template CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
