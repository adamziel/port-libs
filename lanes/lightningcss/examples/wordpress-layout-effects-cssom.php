<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'visibility: Hidden; box-sizing: Border-Box; position: -WebKit-Sticky; text-overflow: Ellipsis; mix-blend-mode: Plus-Lighter; z-index: +0010; aspect-ratio: 16.0 / 9.00; --Editor-State: Hidden';

$actual = [
    'stickyPosition' => $block->getProperty($declarations, 'position'),
    'coverRatio' => $block->getProperty($declarations, 'aspect-ratio'),
    'overlayBlendMode' => $block->getProperty($declarations, 'mix-blend-mode'),
    'editorStateToken' => $block->getProperty($declarations, '--Editor-State'),
    'writeModernSticky' => $block->setProperty($declarations, 'position', 'Sticky'),
    'raiseLayer' => $block->setProperty($declarations, 'z-index', '+0024', true),
];

$expected = [
    'stickyPosition' => ['value' => '-webkit-sticky', 'important' => false],
    'coverRatio' => ['value' => '16 / 9', 'important' => false],
    'overlayBlendMode' => ['value' => 'plus-lighter', 'important' => false],
    'editorStateToken' => ['value' => 'Hidden', 'important' => false],
    'writeModernSticky' => 'visibility: hidden; box-sizing: border-box; position: sticky; text-overflow: ellipsis; mix-blend-mode: plus-lighter; z-index: 10; aspect-ratio: 16 / 9; --Editor-State: Hidden',
    'raiseLayer' => 'visibility: hidden; box-sizing: border-box; position: -webkit-sticky; text-overflow: ellipsis; mix-blend-mode: plus-lighter; aspect-ratio: 16 / 9; --Editor-State: Hidden; z-index: 24 !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected layout/effects CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
