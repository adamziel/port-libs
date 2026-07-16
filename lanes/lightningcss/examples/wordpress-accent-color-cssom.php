<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$controlStyles = 'accent-color: Yellow; caret-color: auto; --wp-control-accent: Yellow';

$actual = [
    'accentColor' => $block->getProperty($controlStyles, 'accent-color'),
    'caretColor' => $block->getProperty($controlStyles, 'caret-color'),
    'customToken' => $block->getProperty($controlStyles, '--wp-control-accent'),
    'systemAccent' => $block->setProperty($controlStyles, 'accent-color', 'AUTO'),
    'focusAccent' => $block->setProperty($controlStyles, 'accent-color', 'Lime', true),
    'withoutAccent' => $block->removeProperty($controlStyles, 'accent-color'),
];

$expected = [
    'accentColor' => ['value' => '#ff0', 'important' => false],
    'caretColor' => ['value' => 'auto', 'important' => false],
    'customToken' => ['value' => 'Yellow', 'important' => false],
    'systemAccent' => 'accent-color: auto; caret-color: auto; --wp-control-accent: Yellow',
    'focusAccent' => 'caret-color: auto; --wp-control-accent: Yellow; accent-color: #0f0 !important',
    'withoutAccent' => 'caret-color: auto; --wp-control-accent: Yellow',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected accent-color CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
