<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$buttonColors = 'color: #FF0000; background-color: RGB(255 255 0 / 100%); outline-color: CURRENTCOLOR !important; caret-color: AUTO; --wp--preset--color--Brand: Yellow';

$actual = [
    'parsed' => $block->parse($buttonColors),
    'textColor' => $block->getProperty($buttonColors, 'color'),
    'backgroundColor' => $block->getProperty($buttonColors, 'background-color'),
    'outlineColor' => $block->getProperty($buttonColors, 'outline-color'),
    'caretColor' => $block->getProperty($buttonColors, 'caret-color'),
    'brandToken' => $block->getProperty($buttonColors, '--wp--preset--color--Brand'),
    'brandText' => $block->setProperty($buttonColors, 'color', 'BLUE', true),
    'contrastCaret' => $block->setProperty($buttonColors, 'caret-color', 'CURRENTCOLOR'),
    'withoutOutline' => $block->removeProperty($buttonColors, 'outline-color'),
];

$expected = [
    'parsed' => [
        'color' => 'red',
        'background-color' => '#ff0',
        'outline-color' => 'currentColor !important',
        'caret-color' => 'auto',
        '--wp--preset--color--Brand' => 'Yellow',
    ],
    'textColor' => ['value' => 'red', 'important' => false],
    'backgroundColor' => ['value' => '#ff0', 'important' => false],
    'outlineColor' => ['value' => 'currentColor', 'important' => true],
    'caretColor' => ['value' => 'auto', 'important' => false],
    'brandToken' => ['value' => 'Yellow', 'important' => false],
    'brandText' => 'background-color: #ff0; caret-color: auto; --wp--preset--color--Brand: Yellow; outline-color: currentColor !important; color: #00f !important',
    'contrastCaret' => 'color: red; background-color: #ff0; caret-color: currentColor; --wp--preset--color--Brand: Yellow; outline-color: currentColor !important',
    'withoutOutline' => 'color: red; background-color: #ff0; caret-color: auto; --wp--preset--color--Brand: Yellow',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected direct color CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
