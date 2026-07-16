<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'animation-name: wp-cover-enter; animation-duration: 240ms; animation-composition: ADD, Accumulate; --Animation-Composition: ADD';

$actual = [
    'composition' => $block->getProperty($declarations, 'animation-composition'),
    'customToken' => $block->getProperty($declarations, '--Animation-Composition'),
    'replaceComposition' => $block->setProperty($declarations, 'animation-composition', 'Replace'),
    'importantComposition' => $block->setProperty($declarations, 'animation-composition', 'Accumulate', true),
    'withoutComposition' => $block->removeProperty($declarations, 'animation-composition'),
];

$expected = [
    'composition' => ['value' => 'add, accumulate', 'important' => false],
    'customToken' => ['value' => 'ADD', 'important' => false],
    'replaceComposition' => 'animation-name: wp-cover-enter; animation-duration: 240ms; animation-composition: replace; --Animation-Composition: ADD',
    'importantComposition' => 'animation-name: wp-cover-enter; animation-duration: 240ms; --Animation-Composition: ADD; animation-composition: accumulate !important',
    'withoutComposition' => 'animation-name: wp-cover-enter; animation-duration: 240ms; --Animation-Composition: ADD',
];

if (in_array('--self-test', $argv, true)) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected animation-composition CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
