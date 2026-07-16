<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$cardBorder = 'border-block: 2px solid var(--wp--preset--color--contrast); border-inline-color: var(--wp--preset--color--primary) var(--wp--preset--color--accent)';

$actual = [
    'blockStartColor' => $block->getProperty($cardBorder, 'border-block-start-color'),
    'inlineColor' => $block->getProperty($cardBorder, 'border-inline-color'),
    'editorBlockStartColor' => $block->setProperty(
        'border-block-color: var(--wp--preset--color--contrast) var(--wp--preset--color--contrast)',
        'border-block-start-color',
        'var(--wp--preset--color--accent)'
    ),
    'editorInlineStyle' => $block->setProperty(
        'border-inline-start: 1px solid var(--wp--preset--color--primary)',
        'border-inline-start-style',
        'dashed'
    ),
    'dropInlineStartColor' => $block->removeProperty(
        'border-inline: 1px solid var(--wp--preset--color--primary); color: var(--wp--preset--color--contrast)',
        'border-inline-start-color'
    ),
];

$expected = [
    'blockStartColor' => [
        'value' => 'var(--wp--preset--color--contrast)',
        'important' => false,
    ],
    'inlineColor' => [
        'value' => 'var(--wp--preset--color--primary) var(--wp--preset--color--accent)',
        'important' => false,
    ],
    'editorBlockStartColor' => 'border-block-color: var(--wp--preset--color--accent) var(--wp--preset--color--contrast)',
    'editorInlineStyle' => 'border-inline-start: 1px dashed var(--wp--preset--color--primary)',
    'dropInlineStartColor' => 'border-inline-start-width: 1px; border-inline-end-width: 1px; border-inline-start-style: solid; border-inline-end-style: solid; border-inline-end-color: var(--wp--preset--color--primary); color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected logical border CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
