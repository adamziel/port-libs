<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box luminance';
$prefixedDeclarations = '-webkit-mask: url("/wp-content/themes/acme/assets/fade.svg") 50% 50% / cover no-repeat content-box padding-box';
$compositingDeclarations = '-webkit-mask-composite: SOURCE-OUT; -webkit-mask-source-type: LUMINANCE; mask-composite: SUBTRACT; mask-mode: LUMINANCE; color: var(--wp--preset--color--contrast)';

$actual = [
    'maskImage' => $block->getProperty($declarations, 'mask-image'),
    'maskFocalX' => $block->getProperty($declarations, 'mask-position-x'),
    'maskMode' => $block->getProperty($declarations, 'mask-mode'),
    'webkitMaskImage' => $block->getProperty($prefixedDeclarations, '-webkit-mask-image'),
    'webkitComposite' => $block->getProperty($compositingDeclarations, '-webkit-mask-composite'),
    'webkitSourceType' => $block->getProperty($compositingDeclarations, '-webkit-mask-source-type'),
    'modernComposite' => $block->getProperty($compositingDeclarations, 'mask-composite'),
    'modernMaskMode' => $block->getProperty($compositingDeclarations, 'mask-mode'),
    'updatedMaskAsset' => $block->setProperty(
        $declarations,
        'mask-image',
        'url("/wp-content/uploads/2026/hero-mask.svg")'
    ),
    'updatedMaskFocalX' => $block->setProperty(
        $declarations,
        'mask-position-x',
        '20%'
    ),
    'updatedWebkitMaskRepeat' => $block->setProperty(
        $prefixedDeclarations,
        '-webkit-mask-repeat',
        'repeat-x'
    ),
    'updatedWebkitComposite' => $block->setProperty(
        $compositingDeclarations,
        '-webkit-mask-composite',
        'Source-In',
        true
    ),
    'updatedModernMode' => $block->setProperty(
        $compositingDeclarations,
        'mask-mode',
        'Alpha'
    ),
    'dropMaskAsset' => $block->removeProperty($declarations, 'mask-image'),
    'dropMaskFocalX' => $block->removeProperty($declarations, 'mask-position-x'),
    'dropWebkitMaskAsset' => $block->removeProperty($prefixedDeclarations, '-webkit-mask-image'),
    'dropWebkitSourceType' => $block->removeProperty($compositingDeclarations, '-webkit-mask-source-type'),
];

$expected = [
    'maskImage' => [
        'value' => 'url(/wp-content/themes/acme/assets/fade.svg)',
        'important' => false,
    ],
    'maskFocalX' => [
        'value' => '50%',
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
    'webkitComposite' => [
        'value' => 'source-out',
        'important' => false,
    ],
    'webkitSourceType' => [
        'value' => 'luminance',
        'important' => false,
    ],
    'modernComposite' => [
        'value' => 'subtract',
        'important' => false,
    ],
    'modernMaskMode' => [
        'value' => 'luminance',
        'important' => false,
    ],
    'updatedMaskAsset' => 'mask: url(/wp-content/uploads/2026/hero-mask.svg) 50% 50% / cover no-repeat content-box padding-box luminance',
    'updatedMaskFocalX' => 'mask: url(/wp-content/themes/acme/assets/fade.svg) 20% 50% / cover no-repeat content-box padding-box luminance',
    'updatedWebkitMaskRepeat' => '-webkit-mask: url(/wp-content/themes/acme/assets/fade.svg) 50% 50% / cover repeat-x content-box padding-box',
    'updatedWebkitComposite' => '-webkit-mask-source-type: luminance; mask-composite: subtract; mask-mode: luminance; color: var(--wp--preset--color--contrast); -webkit-mask-composite: source-in !important',
    'updatedModernMode' => '-webkit-mask-composite: source-out; -webkit-mask-source-type: luminance; mask-composite: subtract; mask-mode: alpha; color: var(--wp--preset--color--contrast)',
    'dropMaskAsset' => 'mask-position: 50% 50%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: add; mask-mode: luminance',
    'dropMaskFocalX' => 'mask-image: url(/wp-content/themes/acme/assets/fade.svg); mask-position-y: 50%; mask-size: cover; mask-repeat: no-repeat; mask-origin: content-box; mask-clip: padding-box; mask-composite: add; mask-mode: luminance',
    'dropWebkitMaskAsset' => '-webkit-mask-position: 50% 50%; -webkit-mask-size: cover; -webkit-mask-repeat: no-repeat; -webkit-mask-origin: content-box; -webkit-mask-clip: padding-box',
    'dropWebkitSourceType' => '-webkit-mask-composite: source-out; mask-composite: subtract; mask-mode: luminance; color: var(--wp--preset--color--contrast)',
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
