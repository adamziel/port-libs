<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'font: oblique +014.000deg 600 16px/1.5 "Inter", sans-serif; letter-spacing: .01em';
$directTypography = 'font-family: "Inter var", system-ui; font-size: +016.00PX; line-height: +001.500; font-weight: +0700; font-stretch: 125.0%; font-variant-caps: All-Small-Caps; --Font-Size: +016.00PX';

$actual = [
    'font' => $block->getProperty($declarations, 'font'),
    'fontFamily' => $block->getProperty($declarations, 'font-family'),
    'expandedWeight' => $block->setProperty($declarations, 'font-weight', '700'),
    'defaultOblique' => $block->setProperty($declarations, 'font-style', 'Oblique 14deg'),
    'themeFamily' => $block->setProperty($declarations, 'font-family', '"Source Serif 4", serif'),
    'dropLineHeight' => $block->removeProperty($declarations, 'line-height'),
    'dropFont' => $block->removeProperty($declarations, 'font'),
    'directTypography' => $block->parse($directTypography),
    'directFontSize' => $block->setProperty('color: var(--wp--preset--color--contrast)', 'font-size', '+018.00PX'),
    'directWeight' => $block->setProperty('color: var(--wp--preset--color--contrast)', 'font-weight', '+0700', true),
    'directLineHeight' => $block->setProperty('color: var(--wp--preset--color--contrast)', 'line-height', '+001.250'),
];

$expected = [
    'font' => [
        'value' => 'oblique 600 16px/1.5 Inter, sans-serif',
        'important' => false,
    ],
    'fontFamily' => [
        'value' => 'Inter, sans-serif',
        'important' => false,
    ],
    'expandedWeight' => 'font: oblique 700 16px/1.5 Inter, sans-serif; letter-spacing: .01em',
    'defaultOblique' => 'font: oblique 600 16px/1.5 Inter, sans-serif; letter-spacing: .01em',
    'themeFamily' => 'font: oblique 600 16px/1.5 "Source Serif 4", serif; letter-spacing: .01em',
    'dropLineHeight' => 'font-family: Inter, sans-serif; font-size: 16px; font-style: oblique; font-weight: 600; font-stretch: normal; font-variant-caps: normal; letter-spacing: .01em',
    'dropFont' => 'letter-spacing: .01em',
    'directTypography' => [
        'font-family' => 'Inter var, system-ui',
        'font-size' => '16px',
        'line-height' => '1.5',
        'font-weight' => '700',
        'font-stretch' => '125%',
        'font-variant-caps' => 'all-small-caps',
        '--Font-Size' => '+016.00PX',
    ],
    'directFontSize' => 'color: var(--wp--preset--color--contrast); font-size: 18px',
    'directWeight' => 'color: var(--wp--preset--color--contrast); font-weight: 700 !important',
    'directLineHeight' => 'color: var(--wp--preset--color--contrast); line-height: 1.25',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
