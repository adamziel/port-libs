<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'margin-inline: var(--wp--preset--spacing--40) 2rem; padding-block: 1rem 2rem';

$actual = [
    'inlineStart' => $block->getProperty($declarations, 'margin-inline-start'),
    'paddingBlock' => $block->getProperty($declarations, 'padding-block'),
    'logicalSpacingAfterPhysicalFallback' => $block->setProperty(
        'margin-inline-start: var(--wp--preset--spacing--40); margin-left: 2rem',
        'margin-inline-start',
        'var(--wp--preset--spacing--50)'
    ),
    'logicalSpacingInShorthand' => $block->setProperty(
        $declarations,
        'margin-inline-start',
        'var(--wp--preset--spacing--50)'
    ),
    'physicalSpacingAfterLogicalFallback' => $block->setProperty(
        'padding-left: 1rem; padding-inline-start: var(--wp--preset--spacing--30)',
        'padding-left',
        '2rem'
    ),
    'removedInlineStart' => $block->removeProperty($declarations, 'margin-inline-start'),
];

$expected = [
    'inlineStart' => [
        'value' => 'var(--wp--preset--spacing--40)',
        'important' => false,
    ],
    'paddingBlock' => [
        'value' => '1rem 2rem',
        'important' => false,
    ],
    'logicalSpacingAfterPhysicalFallback' => 'margin-inline-start: var(--wp--preset--spacing--40); margin-left: 2rem; margin-inline-start: var(--wp--preset--spacing--50)',
    'logicalSpacingInShorthand' => 'margin-inline: var(--wp--preset--spacing--50) 2rem; padding-block: 1rem 2rem',
    'physicalSpacingAfterLogicalFallback' => 'padding-left: 1rem; padding-inline-start: var(--wp--preset--spacing--30); padding-left: 2rem',
    'removedInlineStart' => 'margin-inline-end: 2rem; padding-block: 1rem 2rem',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected logical spacing CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
