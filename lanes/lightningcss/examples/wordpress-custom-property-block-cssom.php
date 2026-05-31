<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = '--wp--custom--button-hover: { color: var(--wp--preset--color--base); background: url("/wp-content/uploads/a;b.svg") }; color: var(--wp--preset--color--contrast); --wp--custom--editor-list: [--focus: 1; --hover: 2] !important';

$actual = [
    'hoverRule' => $block->getProperty($declarations, '--wp--custom--button-hover'),
    'editorList' => $block->getProperty($declarations, '--wp--custom--editor-list'),
    'updatedHoverRule' => $block->setProperty(
        $declarations,
        '--wp--custom--button-hover',
        '{ color: var(--wp--preset--color--contrast); background: url("/wp-content/uploads/cta.svg") }'
    ),
    'removedHoverRule' => $block->removeProperty($declarations, '--wp--custom--button-hover'),
];

$expected = [
    'hoverRule' => [
        'value' => '{ color: var(--wp--preset--color--base); background: url("/wp-content/uploads/a;b.svg") }',
        'important' => false,
    ],
    'editorList' => [
        'value' => '[--focus: 1; --hover: 2]',
        'important' => true,
    ],
    'updatedHoverRule' => '--wp--custom--button-hover: { color: var(--wp--preset--color--contrast); background: url("/wp-content/uploads/cta.svg") }; color: var(--wp--preset--color--contrast); --wp--custom--editor-list: [--focus: 1; --hover: 2] !important',
    'removedHoverRule' => 'color: var(--wp--preset--color--contrast); --wp--custom--editor-list: [--focus: 1; --hover: 2] !important',
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
