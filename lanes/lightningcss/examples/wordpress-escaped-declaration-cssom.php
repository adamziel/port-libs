<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'c\6f lor: var(--wp--preset--color--contrast) !important; --wp\2d\2d preset\2d\2d color\2d\2d accent: #0f0; background-color: white';

$actual = [
    'activeColor' => $block->getProperty($declarations, 'color'),
    'accentToken' => $block->getProperty($declarations, '--wp--preset--color--accent'),
    'orderedNames' => [
        $block->item($declarations, 0),
        $block->item($declarations, 1),
        $block->item($declarations, 2),
    ],
    'editorColor' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)'),
    'removedAccent' => $block->removeProperty($declarations, '--wp--preset--color--accent'),
];

$expected = [
    'activeColor' => ['value' => 'var(--wp--preset--color--contrast)', 'important' => true],
    'accentToken' => ['value' => '#0f0', 'important' => false],
    'orderedNames' => ['--wp--preset--color--accent', 'background-color', 'color'],
    'editorColor' => '--wp--preset--color--accent: #0f0; background-color: white; color: var(--wp--preset--color--primary)',
    'removedAccent' => 'background-color: white; color: var(--wp--preset--color--contrast) !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected escaped declaration CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
