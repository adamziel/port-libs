<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$declarations = 'color: var(--wp--preset--color--accent) ! /* theme safeguard */ important; background: white; color: var(--wp--preset--color--contrast)';
$spacing = 'margin: var(--wp--preset--spacing--40) !/* core spacing */ important; padding: 1rem; margin-top: 0';

$actual = [
    'activeColor' => $block->getProperty($declarations, 'color'),
    'editorOverride' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)'),
    'importantOverride' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)', true),
    'removeTopMargin' => $block->removeProperty($spacing, 'margin-top'),
];

$expected = [
    'activeColor' => ['value' => 'var(--wp--preset--color--accent)', 'important' => true],
    'editorOverride' => 'background: white; color: var(--wp--preset--color--primary)',
    'importantOverride' => 'background: white; color: var(--wp--preset--color--primary) !important',
    'removeTopMargin' => 'padding: 1rem; margin-right: var(--wp--preset--spacing--40) !important; margin-bottom: var(--wp--preset--spacing--40) !important; margin-left: var(--wp--preset--spacing--40) !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSSOM important-comment output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
