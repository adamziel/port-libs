<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$layoutSpacing = 'gap: var(--wp--style--block-gap) 2rem; color: var(--wp--preset--color--contrast)';

$actual = [
    'rowGap' => $block->getProperty($layoutSpacing, 'row-gap'),
    'setEditorColumnGap' => $block->setProperty($layoutSpacing, 'column-gap', '3rem'),
    'dropRowGap' => $block->removeProperty($layoutSpacing, 'row-gap'),
    'resetGap' => $block->removeProperty('gap: 1rem 2rem; row-gap: 3rem; padding: 0', 'gap'),
];

$expected = [
    'rowGap' => [
        'value' => 'var(--wp--style--block-gap)',
        'important' => false,
    ],
    'setEditorColumnGap' => 'gap: var(--wp--style--block-gap) 3rem; color: var(--wp--preset--color--contrast)',
    'dropRowGap' => 'column-gap: 2rem; color: var(--wp--preset--color--contrast)',
    'resetGap' => 'padding: 0',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected gap CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
