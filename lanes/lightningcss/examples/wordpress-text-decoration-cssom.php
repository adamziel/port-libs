<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'text-decoration: underline wavy currentColor 2px; text-underline-offset: .12em';
$legacyDeclarations = '-webkit-text-decoration: underline wavy currentColor 2px; -webkit-text-decoration-skip: objects';

$actual = [
    'linkDecorationColor' => $block->getProperty($declarations, 'text-decoration-color'),
    'accentDecoration' => $block->setProperty(
        $declarations,
        'text-decoration-color',
        'var(--wp--preset--color--accent)'
    ),
    'fromFontThickness' => $block->setProperty($declarations, 'text-decoration-thickness', 'from-font'),
    'colorRemoved' => $block->removeProperty($declarations, 'text-decoration-color'),
    'decorationRemoved' => $block->removeProperty($declarations, 'text-decoration'),
    'legacyLinkDecorationColor' => $block->getProperty($legacyDeclarations, '-webkit-text-decoration-color'),
    'legacyAccentDecoration' => $block->setProperty(
        $legacyDeclarations,
        '-webkit-text-decoration-color',
        'var(--wp--preset--color--accent)'
    ),
    'legacyColorRemoved' => $block->removeProperty($legacyDeclarations, '-webkit-text-decoration-color'),
    'legacyDecorationRemoved' => $block->removeProperty($legacyDeclarations, '-webkit-text-decoration'),
];

$expected = [
    'linkDecorationColor' => [
        'value' => 'currentColor',
        'important' => false,
    ],
    'accentDecoration' => 'text-decoration: underline 2px wavy var(--wp--preset--color--accent); text-underline-offset: .12em',
    'fromFontThickness' => 'text-decoration: underline from-font wavy; text-underline-offset: .12em',
    'colorRemoved' => 'text-decoration-line: underline; text-decoration-thickness: 2px; text-decoration-style: wavy; text-underline-offset: .12em',
    'decorationRemoved' => 'text-underline-offset: .12em',
    'legacyLinkDecorationColor' => [
        'value' => 'currentColor',
        'important' => false,
    ],
    'legacyAccentDecoration' => '-webkit-text-decoration: underline 2px wavy var(--wp--preset--color--accent); -webkit-text-decoration-skip: objects',
    'legacyColorRemoved' => '-webkit-text-decoration-line: underline; -webkit-text-decoration-style: wavy; -webkit-text-decoration-skip: objects',
    'legacyDecorationRemoved' => '-webkit-text-decoration-skip: objects',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text-decoration CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
