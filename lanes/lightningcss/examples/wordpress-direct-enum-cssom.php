<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$themeDeclarations = 'color-scheme: Dark Light Only; print-color-adjust: Exact; view-transition-name: AUTO; view-transition-group: NEAREST; resize: Horizontal; user-select: Text; -webkit-user-select: NONE; appearance: SearchField; -moz-appearance: Menulist-Button; color: var(--wp--preset--color--contrast)';

$actual = [
    'colorScheme' => $block->getProperty($themeDeclarations, 'color-scheme'),
    'printAdjust' => $block->getProperty($themeDeclarations, 'print-color-adjust'),
    'transitionName' => $block->getProperty($themeDeclarations, 'view-transition-name'),
    'transitionGroup' => $block->getProperty($themeDeclarations, 'view-transition-group'),
    'resizeMode' => $block->getProperty($themeDeclarations, 'resize'),
    'selectionMode' => $block->getProperty($themeDeclarations, 'user-select'),
    'webkitSelectionMode' => $block->getProperty($themeDeclarations, '-webkit-user-select'),
    'controlAppearance' => $block->getProperty($themeDeclarations, 'appearance'),
    'mozControlAppearance' => $block->getProperty($themeDeclarations, '-moz-appearance'),
    'darkOnly' => $block->setProperty($themeDeclarations, 'color-scheme', 'ONLY DARK'),
    'economyPrint' => $block->setProperty($themeDeclarations, 'print-color-adjust', 'Economy'),
    'namedTransition' => $block->setProperty($themeDeclarations, 'view-transition-name', 'wp-nav-menu'),
    'blockResize' => $block->setProperty($themeDeclarations, 'resize', 'Block'),
    'disableSelection' => $block->setProperty($themeDeclarations, 'user-select', 'NONE'),
    'nativeTextareaAppearance' => $block->setProperty($themeDeclarations, 'appearance', 'TextArea'),
    'withoutScheme' => $block->removeProperty($themeDeclarations, 'color-scheme'),
    'withoutAppearance' => $block->removeProperty($themeDeclarations, 'appearance'),
];

$expected = [
    'colorScheme' => ['value' => 'light dark only', 'important' => false],
    'printAdjust' => ['value' => 'exact', 'important' => false],
    'transitionName' => ['value' => 'auto', 'important' => false],
    'transitionGroup' => ['value' => 'nearest', 'important' => false],
    'resizeMode' => ['value' => 'horizontal', 'important' => false],
    'selectionMode' => ['value' => 'text', 'important' => false],
    'webkitSelectionMode' => ['value' => 'none', 'important' => false],
    'controlAppearance' => ['value' => 'searchfield', 'important' => false],
    'mozControlAppearance' => ['value' => 'menulist-button', 'important' => false],
    'darkOnly' => 'color-scheme: dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'economyPrint' => 'color-scheme: light dark only; print-color-adjust: economy; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'namedTransition' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: wp-nav-menu; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'blockResize' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: block; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'disableSelection' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: none; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'nativeTextareaAppearance' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; appearance: textarea; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'withoutScheme' => 'print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; appearance: searchfield; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
    'withoutAppearance' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; resize: horizontal; user-select: text; -webkit-user-select: none; -moz-appearance: menulist-button; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected direct enum CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
