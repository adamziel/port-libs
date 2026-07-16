<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$media = 'object-fit: Cover; object-position: CENTER TOP; -o-object-position: LEFT 10PX TOP 20PX; color: var(--wp--preset--color--contrast)';

$actual = [
    'fit' => $block->getProperty($media, 'object-fit'),
    'position' => $block->getProperty($media, 'object-position'),
    'legacyPosition' => $block->getProperty($media, '-o-object-position'),
    'cropHero' => $block->setProperty($media, 'object-fit', 'Scale-Down', true),
    'focalPoint' => $block->setProperty($media, 'object-position', '0PX 50.000%'),
    'dropLegacyPosition' => $block->removeProperty($media, '-o-object-position'),
];

$expected = [
    'fit' => ['value' => 'cover', 'important' => false],
    'position' => ['value' => 'center top', 'important' => false],
    'legacyPosition' => ['value' => 'left 10px top 20px', 'important' => false],
    'cropHero' => 'object-position: center top; -o-object-position: left 10px top 20px; color: var(--wp--preset--color--contrast); object-fit: scale-down !important',
    'focalPoint' => 'object-fit: cover; object-position: 0 50%; -o-object-position: left 10px top 20px; color: var(--wp--preset--color--contrast)',
    'dropLegacyPosition' => 'object-fit: cover; object-position: center top; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected object-fit CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
