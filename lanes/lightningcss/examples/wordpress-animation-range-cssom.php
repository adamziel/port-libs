<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'animation: wp-cover-reveal 500ms ease-out; animation-range: entry exit 90%';
$block = new DeclarationBlock();

$actual = [
    'range' => $block->getProperty($declarations, 'animation-range'),
    'rangeStart' => $block->getProperty($declarations, 'animation-range-start'),
    'rangeEnd' => $block->getProperty($declarations, 'animation-range-end'),
    'tightenedExit' => $block->setProperty($declarations, 'animation-range-end', 'exit 80%'),
    'customStart' => $block->setProperty($declarations, 'animation-range-start', 'cover 15%'),
    'withoutEnd' => $block->removeProperty($declarations, 'animation-range-end'),
    'withoutRange' => $block->removeProperty($declarations, 'animation-range'),
];

$expected = [
    'range' => ['value' => 'entry exit 90%', 'important' => false],
    'rangeStart' => ['value' => 'entry', 'important' => false],
    'rangeEnd' => ['value' => 'exit 90%', 'important' => false],
    'tightenedExit' => 'animation: wp-cover-reveal 500ms ease-out; animation-range: entry exit 80%',
    'customStart' => 'animation: wp-cover-reveal 500ms ease-out; animation-range: cover 15% exit 90%',
    'withoutEnd' => 'animation: wp-cover-reveal 500ms ease-out; animation-range-start: entry',
    'withoutRange' => 'animation: wp-cover-reveal 500ms ease-out',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected animation range CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
