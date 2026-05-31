<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$layout = 'place-content: space-between center; place-items: normal stretch; color: var(--wp--preset--color--contrast)';

$actual = [
    'contentAlignment' => $block->getProperty($layout, 'place-content'),
    'itemsAlignment' => $block->getProperty($layout, 'align-items'),
    'justifyNavigation' => $block->setProperty($layout, 'justify-content', 'flex-end'),
    'legacyEditorItems' => $block->setProperty($layout, 'justify-items', 'right legacy'),
    'contentAlignRemoved' => $block->removeProperty($layout, 'align-content'),
    'itemsRemoved' => $block->removeProperty($layout, 'place-items'),
    'prioritySeparated' => $block->setProperty('place-content: center space-between !important', 'align-content', 'start'),
];

$expected = [
    'contentAlignment' => [
        'value' => 'space-between center',
        'important' => false,
    ],
    'itemsAlignment' => [
        'value' => 'normal',
        'important' => false,
    ],
    'justifyNavigation' => 'place-content: space-between flex-end; place-items: normal stretch; color: var(--wp--preset--color--contrast)',
    'legacyEditorItems' => 'place-content: space-between center; place-items: normal legacy right; color: var(--wp--preset--color--contrast)',
    'contentAlignRemoved' => 'justify-content: center; place-items: normal stretch; color: var(--wp--preset--color--contrast)',
    'itemsRemoved' => 'place-content: space-between center; color: var(--wp--preset--color--contrast)',
    'prioritySeparated' => 'align-content: start; place-content: center space-between !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected place alignment CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
