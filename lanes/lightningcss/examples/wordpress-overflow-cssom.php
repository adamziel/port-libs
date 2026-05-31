<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$scrollPanel = 'overflow: hidden auto; scrollbar-gutter: stable; color: var(--wp--preset--color--contrast)';

$actual = [
    'verticalOverflow' => $block->getProperty($scrollPanel, 'overflow-y'),
    'editorHorizontalScroll' => $block->setProperty($scrollPanel, 'overflow-x', 'scroll'),
    'lockBothAxes' => $block->setProperty($scrollPanel, 'overflow-y', 'hidden'),
    'dropHorizontalOverflow' => $block->removeProperty($scrollPanel, 'overflow-x'),
    'resetOverflow' => $block->removeProperty('overflow: hidden auto; overflow-x: clip; padding: var(--wp--preset--spacing--40)', 'overflow'),
];

$expected = [
    'verticalOverflow' => [
        'value' => 'auto',
        'important' => false,
    ],
    'editorHorizontalScroll' => 'overflow: scroll auto; scrollbar-gutter: stable; color: var(--wp--preset--color--contrast)',
    'lockBothAxes' => 'overflow: hidden; scrollbar-gutter: stable; color: var(--wp--preset--color--contrast)',
    'dropHorizontalOverflow' => 'overflow-y: auto; scrollbar-gutter: stable; color: var(--wp--preset--color--contrast)',
    'resetOverflow' => 'padding: var(--wp--preset--spacing--40)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected overflow CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
