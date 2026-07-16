<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$typography = 'font-palette: --\57 P-Duotone; font-family: Bixa; --wp-font-palette: --\57 P-Duotone';

$actual = [
    'themePalette' => $block->getProperty($typography, 'font-palette'),
    'customToken' => $block->getProperty($typography, '--wp-font-palette'),
    'editorPalette' => $block->setProperty($typography, 'font-palette', '--wp-editor-palette'),
    'escapedEditorPalette' => $block->setProperty($typography, 'font-palette', '--\57 P-Highlight', true),
    'withoutPalette' => $block->removeProperty($typography, 'font-palette'),
];

$expected = [
    'themePalette' => ['value' => '--WP-Duotone', 'important' => false],
    'customToken' => ['value' => '--\57 P-Duotone', 'important' => false],
    'editorPalette' => 'font-palette: --wp-editor-palette; font-family: Bixa; --wp-font-palette: --\57 P-Duotone',
    'escapedEditorPalette' => 'font-family: Bixa; --wp-font-palette: --\57 P-Duotone; font-palette: --WP-Highlight !important',
    'withoutPalette' => 'font-family: Bixa; --wp-font-palette: --\57 P-Duotone',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font palette CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
