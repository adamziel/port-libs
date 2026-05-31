<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require_once __DIR__ . '/../src/DeclarationBlock.php';

$block = new DeclarationBlock();
$button = 'flex: 1 0 auto; gap: var(--wp--style--block-gap)';
$legacyButton = '-ms-flex-flow: row wrap; gap: var(--wp--style--block-gap)';

$actual = [
    'basis' => $block->getProperty($button, 'flex-basis'),
    'fixedBasis' => $block->setProperty($button, 'flex-basis', '12rem'),
    'expandedGrow' => $block->setProperty($button, 'flex-grow', '2'),
    'withoutShrink' => $block->removeProperty($button, 'flex-shrink'),
    'dropFlex' => $block->removeProperty($button, 'flex'),
    'legacyFlow' => $block->getProperty($legacyButton, '-ms-flex-flow'),
    'legacyColumn' => $block->setProperty($legacyButton, '-ms-flex-direction', 'column'),
    'legacyWithoutDirection' => $block->removeProperty($legacyButton, '-ms-flex-direction'),
];

$expected = [
    'basis' => [
        'value' => 'auto',
        'important' => false,
    ],
    'fixedBasis' => 'flex: 1 0 12rem; gap: var(--wp--style--block-gap)',
    'expandedGrow' => 'flex: 2 0 auto; gap: var(--wp--style--block-gap)',
    'withoutShrink' => 'flex-grow: 1; flex-basis: auto; gap: var(--wp--style--block-gap)',
    'dropFlex' => 'gap: var(--wp--style--block-gap)',
    'legacyFlow' => [
        'value' => 'row wrap',
        'important' => false,
    ],
    'legacyColumn' => '-ms-flex-flow: column wrap; gap: var(--wp--style--block-gap)',
    'legacyWithoutDirection' => '-ms-flex-wrap: wrap; gap: var(--wp--style--block-gap)',
];

if (in_array('--self-test', $argv, true)) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected flex CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
