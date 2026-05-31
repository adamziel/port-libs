<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'ALL: INITIAL; color: var(--wp--preset--color--contrast); --wp--custom--reset: var(--theme-reset)';

$actual = [
    'themeReset' => $block->getProperty($declarations, 'all'),
    'editorReset' => $block->setProperty($declarations, 'all', 'REVERT-LAYER'),
    'customResetToken' => $block->setProperty($declarations, 'all', 'var(--wp--custom--reset)'),
    'removedReset' => $block->removeProperty($declarations, 'all'),
];

$expected = [
    'themeReset' => ['value' => 'initial', 'important' => false],
    'editorReset' => 'all: revert-layer; color: var(--wp--preset--color--contrast); --wp--custom--reset: var(--theme-reset)',
    'customResetToken' => 'all: var(--wp--custom--reset); color: var(--wp--preset--color--contrast); --wp--custom--reset: var(--theme-reset)',
    'removedReset' => 'color: var(--wp--preset--color--contrast); --wp--custom--reset: var(--theme-reset)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected all CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
