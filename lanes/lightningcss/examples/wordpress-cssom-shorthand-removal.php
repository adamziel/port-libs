<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$actual = [
    'resetBorderGroup' => $block->removeProperty(
        'border: 1px solid var(--wp--preset--color--contrast); border-top-color: var(--wp--preset--color--accent); padding: var(--wp--preset--spacing--40); border-left-width: 4px',
        'border'
    ),
    'resetFlexFlowGroup' => $block->removeProperty(
        'flex-flow: column wrap; flex-direction: row; flex-wrap: nowrap; gap: var(--wp--preset--spacing--30)',
        'flex-flow'
    ),
    'resetGridAreaGroup' => $block->removeProperty(
        'grid-area: header / main / footer / aside; grid-row-start: promo; grid-column-end: rail; color: var(--wp--preset--color--contrast)',
        'grid-area'
    ),
];

$expected = [
    'resetBorderGroup' => 'padding: var(--wp--preset--spacing--40)',
    'resetFlexFlowGroup' => 'gap: var(--wp--preset--spacing--30)',
    'resetGridAreaGroup' => 'color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSSOM shorthand removal output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
