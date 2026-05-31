<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'mask-border: linear-gradient(var(--wp--preset--color--contrast), transparent) 28 round';
$block = new DeclarationBlock();

$actual = [
    'maskBorderSource' => $block->getProperty($declarations, 'mask-border-source'),
    'maskBorderRepeat' => $block->getProperty($declarations, 'mask-border-repeat'),
    'overriddenSource' => $block->getProperty(
        $declarations . '; mask-border-source: url("/wp-content/themes/acme/assets/frame.svg")',
        'mask-border-source'
    ),
    'updatedFrameAsset' => $block->setProperty(
        $declarations,
        'mask-border-source',
        'url("/wp-content/themes/acme/assets/frame.svg")'
    ),
    'dropFrameAsset' => $block->removeProperty($declarations, 'mask-border-source'),
];

$expected = [
    'maskBorderSource' => [
        'value' => 'linear-gradient(var(--wp--preset--color--contrast), transparent)',
        'important' => false,
    ],
    'maskBorderRepeat' => [
        'value' => 'round',
        'important' => false,
    ],
    'overriddenSource' => [
        'value' => 'url(/wp-content/themes/acme/assets/frame.svg)',
        'important' => false,
    ],
    'updatedFrameAsset' => 'mask-border: url(/wp-content/themes/acme/assets/frame.svg) 28 round',
    'dropFrameAsset' => 'mask-border-slice: 28; mask-border-width: 1; mask-border-outset: 0; mask-border-repeat: round; mask-border-mode: alpha',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected mask-border CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
