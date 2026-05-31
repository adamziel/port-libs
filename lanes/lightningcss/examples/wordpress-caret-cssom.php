<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'caret-color: var(--wp--preset--color--accent); caret-shape: block; color: var(--wp--preset--color--contrast)';

$actual = [
    'caret' => $block->getProperty($declarations, 'caret'),
    'caretShape' => $block->getProperty($declarations, 'caret-shape'),
    'highContrastCaret' => $block->setProperty($declarations, 'caret-color', 'currentColor'),
    'dropCaretColor' => $block->removeProperty($declarations, 'caret-color'),
    'dropCaret' => $block->removeProperty($declarations, 'caret'),
];

$expected = [
    'caret' => [
        'value' => 'var(--wp--preset--color--accent) block',
        'important' => false,
    ],
    'caretShape' => [
        'value' => 'block',
        'important' => false,
    ],
    'highContrastCaret' => 'caret-color: currentColor; caret-shape: block; color: var(--wp--preset--color--contrast)',
    'dropCaretColor' => 'caret-shape: block; color: var(--wp--preset--color--contrast)',
    'dropCaret' => 'color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected caret CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
