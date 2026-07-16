<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$button = 'transition: opacity 200ms ease-in 50ms; color: var(--wp--preset--color--contrast)';
$mismatched = 'transition: color 200ms; color: var(--wp--preset--color--primary)';
$legacyMenu = '-ms-transition: opacity 200ms ease-in 50ms; color: var(--wp--preset--color--contrast)';
$editorMotion = 'transition: Color 200MS Ease-In 50MS; color: var(--wp--preset--color--contrast)';

$actual = [
    'duration' => $block->getProperty($button, 'transition-duration'),
    'motionTiming' => $block->getProperty($button, 'transition-timing-function'),
    'updatedDuration' => $block->setProperty($button, 'transition-duration', '300ms'),
    'mismatchedPropertyList' => $block->setProperty($mismatched, 'transition-property', 'opacity, transform'),
    'canonicalTransition' => $block->getProperty($editorMotion, 'transition'),
    'canonicalPropertyName' => $block->setProperty($editorMotion, 'transition-property', 'Background-Color'),
    'removedDuration' => $block->removeProperty($button, 'transition-duration'),
    'legacyDuration' => $block->getProperty($legacyMenu, '-ms-transition-duration'),
    'legacyUpdatedDelay' => $block->setProperty($legacyMenu, '-ms-transition-delay', '75ms'),
    'legacyRemovedDuration' => $block->removeProperty($legacyMenu, '-ms-transition-duration'),
];

$expected = [
    'duration' => ['value' => '200ms', 'important' => false],
    'motionTiming' => ['value' => 'ease-in', 'important' => false],
    'updatedDuration' => 'transition: opacity 300ms ease-in 50ms; color: var(--wp--preset--color--contrast)',
    'mismatchedPropertyList' => 'transition: color 200ms; color: var(--wp--preset--color--primary); transition-property: opacity, transform',
    'canonicalTransition' => ['value' => 'color 200ms ease-in 50ms', 'important' => false],
    'canonicalPropertyName' => 'transition: background-color 200ms ease-in 50ms; color: var(--wp--preset--color--contrast)',
    'removedDuration' => 'transition-property: opacity; transition-delay: 50ms; transition-timing-function: ease-in; color: var(--wp--preset--color--contrast)',
    'legacyDuration' => ['value' => '200ms', 'important' => false],
    'legacyUpdatedDelay' => '-ms-transition: opacity 200ms ease-in 75ms; color: var(--wp--preset--color--contrast)',
    'legacyRemovedDuration' => '-ms-transition-property: opacity; -ms-transition-delay: 50ms; -ms-transition-timing-function: ease-in; color: var(--wp--preset--color--contrast)',
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
