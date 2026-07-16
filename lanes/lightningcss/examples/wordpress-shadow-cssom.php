<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$themeShadow = 'box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4); text-shadow: 1px 1px 0 yellow';
$editorOverride = 'text-shadow: 2px 2px blue';

$actual = [
    'cardShadow' => $block->getProperty($themeShadow, 'box-shadow'),
    'headingShadow' => $block->getProperty($themeShadow, 'text-shadow'),
    'editorOverride' => $block->setProperty(
        $editorOverride,
        'box-shadow',
        '0px 0px 12px 4px rgba(0,0,0,0.4) inset'
    ),
    'withoutCardShadow' => $block->removeProperty($themeShadow, 'box-shadow'),
];

$expected = [
    'cardShadow' => ['value' => '12px 12px #0006', 'important' => false],
    'headingShadow' => ['value' => '1px 1px #ff0', 'important' => false],
    'editorOverride' => 'text-shadow: 2px 2px #00f; box-shadow: inset 0 0 12px 4px #0006',
    'withoutCardShadow' => 'text-shadow: 1px 1px #ff0',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected shadow CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
