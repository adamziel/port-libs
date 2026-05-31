<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$declarations = '--Block-Accent: var(--wp--preset--color--contrast); --block-accent: var(--wp--preset--color--accent); color: var(--Block-Accent)';

$actual = [
    'readBlockAccent' => $block->getProperty($declarations, '--Block-Accent'),
    'readLowercaseAccent' => $block->getProperty($declarations, '--block-accent'),
    'rewriteBlockAccent' => $block->setProperty(
        $declarations,
        '--Block-Accent',
        'var(--wp--preset--color--primary)'
    ),
    'importantBlockAccent' => $block->setProperty(
        $declarations,
        '--Block-Accent',
        'var(--wp--preset--color--primary)',
        true
    ),
    'removeBlockAccent' => $block->removeProperty($declarations, '--Block-Accent'),
];

$expected = [
    'readBlockAccent' => ['value' => 'var(--wp--preset--color--contrast)', 'important' => false],
    'readLowercaseAccent' => ['value' => 'var(--wp--preset--color--accent)', 'important' => false],
    'rewriteBlockAccent' => '--Block-Accent: var(--wp--preset--color--primary); --block-accent: var(--wp--preset--color--accent); color: var(--Block-Accent)',
    'importantBlockAccent' => '--block-accent: var(--wp--preset--color--accent); color: var(--Block-Accent); --Block-Accent: var(--wp--preset--color--primary) !important',
    'removeBlockAccent' => '--block-accent: var(--wp--preset--color--accent); color: var(--Block-Accent)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected custom property CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
