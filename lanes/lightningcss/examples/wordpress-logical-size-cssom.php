<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$coverSizing = 'block-size: min(80vh, 640px); width: 100%';

$actual = [
    'coverBlockSize' => $block->getProperty($coverSizing, 'block-size'),
    'editorBlockSizeOverride' => $block->setProperty(
        $coverSizing,
        'block-size',
        'clamp(20rem, 75vh, 48rem)'
    ),
    'queryInlineSizeOverride' => $block->setProperty(
        'inline-size: min(100%, 72rem); height: auto',
        'inline-size',
        'min(100%, 80rem)'
    ),
    'editorMaxHeightFallback' => $block->setProperty(
        'max-height: 80vh; max-block-size: 60rem',
        'max-height',
        '70vh'
    ),
];

$expected = [
    'coverBlockSize' => [
        'value' => 'min(80vh, 640px)',
        'important' => false,
    ],
    'editorBlockSizeOverride' => 'block-size: min(80vh, 640px); width: 100%; block-size: clamp(20rem, 75vh, 48rem)',
    'queryInlineSizeOverride' => 'inline-size: min(100%, 72rem); height: auto; inline-size: min(100%, 80rem)',
    'editorMaxHeightFallback' => 'max-height: 80vh; max-block-size: 60rem; max-height: 70vh',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected logical size CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
