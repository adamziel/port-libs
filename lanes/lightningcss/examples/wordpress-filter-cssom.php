<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'filter: URL("/wp-content/uploads/filters.svg#soft-focus") Blur(0px) Brightness(100%) drop-shadow(16px 16px 20px yellow) !important; backdrop-filter: Blur(0px); --Block-Filter: Blur(0px)';

$actual = [
    'themeFilter' => $block->getProperty($declarations, 'filter'),
    'glassBackdrop' => $block->getProperty($declarations, 'backdrop-filter'),
    'themeToken' => $block->getProperty($declarations, '--Block-Filter'),
    'writeBackdrop' => $block->setProperty($declarations, 'backdrop-filter', 'Blur(0px) Brightness(10%)'),
    'dropBackdrop' => $block->removeProperty($declarations, 'backdrop-filter'),
];

$expected = [
    'themeFilter' => [
        'value' => 'url(/wp-content/uploads/filters.svg#soft-focus) blur()brightness() drop-shadow(16px 16px 20px #ff0)',
        'important' => true,
    ],
    'glassBackdrop' => ['value' => 'blur()', 'important' => false],
    'themeToken' => ['value' => 'Blur(0px)', 'important' => false],
    'writeBackdrop' => 'backdrop-filter: blur()brightness(10%); --Block-Filter: Blur(0px); filter: url(/wp-content/uploads/filters.svg#soft-focus) blur()brightness() drop-shadow(16px 16px 20px #ff0) !important',
    'dropBackdrop' => '--Block-Filter: Blur(0px); filter: url(/wp-content/uploads/filters.svg#soft-focus) blur()brightness() drop-shadow(16px 16px 20px #ff0) !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected filter CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
