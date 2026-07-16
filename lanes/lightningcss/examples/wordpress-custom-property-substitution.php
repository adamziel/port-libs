<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$minifier = new CssMinifier();

$themeTokens = [
    '--wp--preset--color--accent' => 'yellow',
    '--wp--custom--button-base' => '12px',
    '--wp--custom--button-extra' => '8px',
    '--wp--custom--button-color' => 'var(--wp--preset--color--accent)',
    '--wp--custom--cycle-a' => 'var(--wp--custom--cycle-b)',
    '--wp--custom--cycle-b' => 'var(--wp--custom--cycle-a)',
];

$actual = [
    'accentColor' => $minifier->substituteVariables('color', 'var(--wp--custom--button-color, blue)', $themeTokens),
    'buttonPadding' => $minifier->substituteVariables('padding-inline', 'calc(var(--wp--custom--button-base) + var(--wp--custom--button-extra))', $themeTokens),
    'fallbackGradient' => $minifier->substituteVariables('background', 'var(--wp--custom--hero-gradient, linear-gradient(yellow, blue))', $themeTokens),
    'cycleGuard' => $minifier->substituteVariables('color', 'var(--wp--custom--cycle-a)', $themeTokens),
];

$expected = [
    'accentColor' => 'color: #ff0',
    'buttonPadding' => 'padding-inline: 20px',
    'fallbackGradient' => 'background: linear-gradient(#ff0,#00f)',
    'cycleGuard' => 'color: var(--wp--custom--cycle-a)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected custom property substitution output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . PHP_EOL;
