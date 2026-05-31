<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box luminance';

$actual = [
    'maskImage' => $block->getProperty($declarations, 'mask-image'),
    'maskMode' => $block->getProperty($declarations, 'mask-mode'),
    'updatedMaskAsset' => $block->setProperty(
        $declarations,
        'mask-image',
        'url("/wp-content/uploads/2026/hero-mask.svg")'
    ),
    'dropMaskAsset' => $block->removeProperty($declarations, 'mask-image'),
];

$expected = [
    'maskImage' => [
        'value' => 'url(/wp-content/themes/acme/assets/fade.svg)',
        'important' => false,
    ],
    'maskMode' => [
        'value' => 'luminance',
        'important' => false,
    ],
    'updatedMaskAsset' => 'mask: url(/wp-content/uploads/2026/hero-mask.svg) 50% 50% / cover no-repeat content-box padding-box luminance',
    'dropMaskAsset' => 'mask-position: 50% 50%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: add; mask-mode: luminance',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected mask CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
