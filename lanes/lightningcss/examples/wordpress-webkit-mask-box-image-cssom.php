<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = '-webkit-mask-box-image: url("/wp-content/themes/acme/assets/frame.svg") 24 / 8px round';
$block = new DeclarationBlock();

$actual = [
    'legacyFrameSource' => $block->getProperty($declarations, '-webkit-mask-box-image-source'),
    'legacyFrameRepeat' => $block->getProperty($declarations, '-webkit-mask-box-image-repeat'),
    'updatedLegacyFrame' => $block->setProperty(
        $declarations,
        '-webkit-mask-box-image-source',
        'url("/wp-content/themes/acme/assets/frame-alt.svg")'
    ),
    'dropLegacyFrameAsset' => $block->removeProperty($declarations, '-webkit-mask-box-image-source'),
    'modernMaskBorderIsolated' => $block->getProperty($declarations, 'mask-border-source'),
];

$expected = [
    'legacyFrameSource' => [
        'value' => 'url(/wp-content/themes/acme/assets/frame.svg)',
        'important' => false,
    ],
    'legacyFrameRepeat' => [
        'value' => 'round',
        'important' => false,
    ],
    'updatedLegacyFrame' => '-webkit-mask-box-image: url(/wp-content/themes/acme/assets/frame-alt.svg) 24 / 8px round',
    'dropLegacyFrameAsset' => '-webkit-mask-box-image-slice: 24; -webkit-mask-box-image-width: 8px; -webkit-mask-box-image-outset: 0; -webkit-mask-box-image-repeat: round',
    'modernMaskBorderIsolated' => null,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected -webkit-mask-box-image CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
