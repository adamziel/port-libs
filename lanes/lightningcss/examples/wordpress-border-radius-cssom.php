<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$card = 'border-radius: 8px 16px / 4px 12px; overflow: hidden';
$logicalFallback = 'border-radius: 8px; border-start-start-radius: var(--wp--custom--radius-start); overflow: hidden';

$actual = [
    'firstCorner' => $block->getProperty($card, 'border-top-left-radius'),
    'editorCorner' => $block->setProperty($card, 'border-top-left-radius', '24px 10px'),
    'prefixedFallback' => $block->setProperty('-webkit-border-radius: 8px; border-radius: 8px', '-webkit-border-bottom-right-radius', '16px'),
    'cornerRemoved' => $block->removeProperty($card, 'border-top-left-radius'),
    'physicalGroupRemoved' => $block->removeProperty($logicalFallback, 'border-radius'),
];

$expected = [
    'firstCorner' => [
        'value' => '8px 4px',
        'important' => false,
    ],
    'editorCorner' => 'border-radius: 24px 16px 8px / 10px 12px 4px; overflow: hidden',
    'prefixedFallback' => '-webkit-border-radius: 8px 8px 16px; border-radius: 8px',
    'cornerRemoved' => 'border-top-right-radius: 16px 12px; border-bottom-right-radius: 8px 4px; border-bottom-left-radius: 16px 12px; overflow: hidden',
    'physicalGroupRemoved' => 'border-start-start-radius: var(--wp--custom--radius-start); overflow: hidden',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected border-radius CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
