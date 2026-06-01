<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'opacity: 50%; fill-opacity: 100%; stroke-opacity: 0.2500; --wp-icon-opacity: 50%';

$actual = [
    'blockOpacity' => $block->getProperty($declarations, 'opacity'),
    'fillOpacity' => $block->getProperty($declarations, 'fill-opacity'),
    'strokeOpacity' => $block->getProperty($declarations, 'stroke-opacity'),
    'customToken' => $block->getProperty($declarations, '--wp-icon-opacity'),
    'mutedIcon' => $block->setProperty($declarations, 'opacity', '25%'),
    'strongStroke' => $block->setProperty($declarations, 'stroke-opacity', '100%', true),
];

$expected = [
    'blockOpacity' => ['value' => '.5', 'important' => false],
    'fillOpacity' => ['value' => '1', 'important' => false],
    'strokeOpacity' => ['value' => '.25', 'important' => false],
    'customToken' => ['value' => '50%', 'important' => false],
    'mutedIcon' => 'opacity: .25; fill-opacity: 1; stroke-opacity: .25; --wp-icon-opacity: 50%',
    'strongStroke' => 'opacity: .5; fill-opacity: 1; --wp-icon-opacity: 50%; stroke-opacity: 1 !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected alpha opacity CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
