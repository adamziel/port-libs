<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$button = 'transition: opacity 200ms ease-in 50ms; color: var(--wp--preset--color--contrast)';
$mismatched = 'transition: color 200ms; color: var(--wp--preset--color--primary)';

$actual = [
    'duration' => $block->getProperty($button, 'transition-duration'),
    'motionTiming' => $block->getProperty($button, 'transition-timing-function'),
    'updatedDuration' => $block->setProperty($button, 'transition-duration', '300ms'),
    'mismatchedPropertyList' => $block->setProperty($mismatched, 'transition-property', 'opacity, transform'),
    'removedDuration' => $block->removeProperty($button, 'transition-duration'),
];

$expected = [
    'duration' => ['value' => '200ms', 'important' => false],
    'motionTiming' => ['value' => 'ease-in', 'important' => false],
    'updatedDuration' => 'transition: opacity 300ms ease-in 50ms; color: var(--wp--preset--color--contrast)',
    'mismatchedPropertyList' => 'transition: color 200ms; color: var(--wp--preset--color--primary); transition-property: opacity, transform',
    'removedDuration' => 'transition-property: opacity; transition-delay: 50ms; transition-timing-function: ease-in; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected transition CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
