<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$themeDeclarations = 'color-scheme: Dark Light Only; print-color-adjust: Exact; view-transition-name: AUTO; view-transition-group: NEAREST; color: var(--wp--preset--color--contrast)';

$actual = [
    'colorScheme' => $block->getProperty($themeDeclarations, 'color-scheme'),
    'printAdjust' => $block->getProperty($themeDeclarations, 'print-color-adjust'),
    'transitionName' => $block->getProperty($themeDeclarations, 'view-transition-name'),
    'transitionGroup' => $block->getProperty($themeDeclarations, 'view-transition-group'),
    'darkOnly' => $block->setProperty($themeDeclarations, 'color-scheme', 'ONLY DARK'),
    'economyPrint' => $block->setProperty($themeDeclarations, 'print-color-adjust', 'Economy'),
    'namedTransition' => $block->setProperty($themeDeclarations, 'view-transition-name', 'wp-nav-menu'),
    'withoutScheme' => $block->removeProperty($themeDeclarations, 'color-scheme'),
];

$expected = [
    'colorScheme' => ['value' => 'light dark only', 'important' => false],
    'printAdjust' => ['value' => 'exact', 'important' => false],
    'transitionName' => ['value' => 'auto', 'important' => false],
    'transitionGroup' => ['value' => 'nearest', 'important' => false],
    'darkOnly' => 'color-scheme: dark only; print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; color: var(--wp--preset--color--contrast)',
    'economyPrint' => 'color-scheme: light dark only; print-color-adjust: economy; view-transition-name: auto; view-transition-group: nearest; color: var(--wp--preset--color--contrast)',
    'namedTransition' => 'color-scheme: light dark only; print-color-adjust: exact; view-transition-name: wp-nav-menu; view-transition-group: nearest; color: var(--wp--preset--color--contrast)',
    'withoutScheme' => 'print-color-adjust: exact; view-transition-name: auto; view-transition-group: nearest; color: var(--wp--preset--color--contrast)',
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
