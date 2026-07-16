<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$maskStyles = 'mask-type: LUMINANCE; --wp--custom--mask-type: LUMINANCE; color: var(--wp--preset--color--contrast)';

$actual = [
    'maskType' => $block->getProperty($maskStyles, 'mask-type'),
    'customMaskType' => $block->getProperty($maskStyles, '--wp--custom--mask-type'),
    'alphaMaskType' => $block->setProperty($maskStyles, 'mask-type', 'Alpha', true),
    'withoutMaskType' => $block->removeProperty($maskStyles, 'mask-type'),
];

$expected = [
    'maskType' => ['value' => 'luminance', 'important' => false],
    'customMaskType' => ['value' => 'LUMINANCE', 'important' => false],
    'alphaMaskType' => '--wp--custom--mask-type: LUMINANCE; color: var(--wp--preset--color--contrast); mask-type: alpha !important',
    'withoutMaskType' => '--wp--custom--mask-type: LUMINANCE; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected mask-type CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
