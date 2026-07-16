<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = '/* theme override ; */ color: var(--wp--preset--color--accent) /* keep */ ! /* core */ important; background: white; color: var(--wp--preset--color--contrast)';
$mixedSpacing = 'margin: var(--wp--preset--spacing--40); margin-top: 0 !important; margin-right: 0 !important; margin-bottom: 0 !important; margin-left: 0 !important';
$mixedFlex = 'flex-flow: row wrap; flex-direction: column !important; flex-wrap: nowrap !important';

$actual = [
    'activeColor' => $block->getProperty($declarations, 'color'),
    'editorOverride' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)'),
    'spacingOverride' => $block->setProperty('margin: var(--wp--preset--spacing--40) /* user reset */ !important', 'margin-top', '0'),
    'spacingShorthandWhenPriorityMixed' => $block->getProperty($mixedSpacing, 'margin'),
    'spacingTopWhenPriorityMixed' => $block->getProperty($mixedSpacing, 'margin-top'),
    'flexFlowWhenPriorityMixed' => $block->getProperty($mixedFlex, 'flex-flow'),
    'removedColor' => $block->removeProperty($declarations, 'color'),
];

$expected = [
    'activeColor' => [
        'value' => 'var(--wp--preset--color--accent)',
        'important' => true,
    ],
    'editorOverride' => 'background: white; color: var(--wp--preset--color--primary)',
    'spacingOverride' => 'margin-top: 0; margin: var(--wp--preset--spacing--40) !important',
    'spacingShorthandWhenPriorityMixed' => null,
    'spacingTopWhenPriorityMixed' => [
        'value' => '0',
        'important' => true,
    ],
    'flexFlowWhenPriorityMixed' => null,
    'removedColor' => 'background: white',
];

if (in_array('--self-test', $argv, true)) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSSOM priority output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
