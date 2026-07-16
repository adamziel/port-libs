<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'display: Inline Flow-Root; visibility: Collapse; position: Sticky; box-sizing: Border-Box; transform-box: Fill-Box; vertical-align: SUPER; perspective: 0px; --wp--custom--display: Inline Flow-Root';
$prefixedMotion = '-webkit-transform-style: Preserve-3D; -moz-backface-visibility: Hidden; -webkit-perspective: +0800.00PX; color: var(--wp--preset--color--contrast)';

$actual = [
    'canonicalDisplay' => $block->getProperty($declarations, 'display'),
    'layoutState' => $block->parse($declarations),
    'themeOverride' => $block->setProperty($declarations, 'display', 'inline flex'),
    'hiddenVariant' => $block->setProperty($declarations, 'visibility', 'Hidden', true),
    'prefixedMotionState' => $block->parse($prefixedMotion),
    'legacyPerspective' => $block->getProperty($prefixedMotion, '-webkit-perspective'),
    'flatLegacyStyle' => $block->setProperty($prefixedMotion, '-webkit-transform-style', 'Flat'),
    'visibleMozBackface' => $block->setProperty($prefixedMotion, '-moz-backface-visibility', 'Visible', true),
    'withoutLegacyPerspective' => $block->removeProperty($prefixedMotion, '-webkit-perspective'),
];

$expected = [
    'canonicalDisplay' => ['value' => 'inline-block', 'important' => false],
    'layoutState' => [
        'display' => 'inline-block',
        'visibility' => 'collapse',
        'position' => 'sticky',
        'box-sizing' => 'border-box',
        'transform-box' => 'fill-box',
        'vertical-align' => 'super',
        'perspective' => '0',
        '--wp--custom--display' => 'Inline Flow-Root',
    ],
    'themeOverride' => 'display: inline-flex; visibility: collapse; position: sticky; box-sizing: border-box; transform-box: fill-box; vertical-align: super; perspective: 0; --wp--custom--display: Inline Flow-Root',
    'hiddenVariant' => 'display: inline-block; position: sticky; box-sizing: border-box; transform-box: fill-box; vertical-align: super; perspective: 0; --wp--custom--display: Inline Flow-Root; visibility: hidden !important',
    'prefixedMotionState' => [
        '-webkit-transform-style' => 'preserve-3d',
        '-moz-backface-visibility' => 'hidden',
        '-webkit-perspective' => '800px',
        'color' => 'var(--wp--preset--color--contrast)',
    ],
    'legacyPerspective' => ['value' => '800px', 'important' => false],
    'flatLegacyStyle' => '-webkit-transform-style: flat; -moz-backface-visibility: hidden; -webkit-perspective: 800px; color: var(--wp--preset--color--contrast)',
    'visibleMozBackface' => '-webkit-transform-style: preserve-3d; -webkit-perspective: 800px; color: var(--wp--preset--color--contrast); -moz-backface-visibility: visible !important',
    'withoutLegacyPerspective' => '-webkit-transform-style: preserve-3d; -moz-backface-visibility: hidden; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected display layout CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
