<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box luminance';
$prefixedDeclarations = '-webkit-mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box';

$actual = [
    'maskImage' => $block->getProperty($declarations, 'mask-image'),
    'maskMode' => $block->getProperty($declarations, 'mask-mode'),
    'webkitMaskImage' => $block->getProperty($prefixedDeclarations, '-webkit-mask-image'),
    'updatedMaskAsset' => $block->setProperty(
        $declarations,
        'mask-image',
        'url("/wp-content/uploads/2026/hero-mask.svg")'
    ),
    'updatedWebkitMaskRepeat' => $block->setProperty(
        $prefixedDeclarations,
        '-webkit-mask-repeat',
        'repeat-x'
    ),
    'dropMaskAsset' => $block->removeProperty($declarations, 'mask-image'),
    'dropWebkitMaskAsset' => $block->removeProperty($prefixedDeclarations, '-webkit-mask-image'),
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
    'webkitMaskImage' => [
        'value' => 'url(/wp-content/themes/acme/assets/fade.svg)',
        'important' => false,
    ],
    'updatedMaskAsset' => 'mask: url(/wp-content/uploads/2026/hero-mask.svg) 50% 50% / cover no-repeat content-box padding-box luminance',
    'updatedWebkitMaskRepeat' => '-webkit-mask: url(/wp-content/themes/acme/assets/fade.svg) 50% 50% / cover repeat-x content-box padding-box',
    'dropMaskAsset' => 'mask-position: 50% 50%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: add; mask-mode: luminance',
    'dropWebkitMaskAsset' => '-webkit-mask-position: 50% 50%; -webkit-mask-size: cover; -webkit-mask-repeat: no-repeat; -webkit-mask-origin: content-box; -webkit-mask-clip: padding-box',
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
