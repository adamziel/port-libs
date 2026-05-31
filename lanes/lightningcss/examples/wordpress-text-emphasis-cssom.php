<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$annotation = 'text-emphasis: dot var(--wp--preset--color--accent); text-emphasis-position: over; color: var(--wp--preset--color--contrast)';

$actual = [
    'emphasisStyle' => $block->getProperty($annotation, 'text-emphasis-style'),
    'primaryEmphasis' => $block->setProperty(
        $annotation,
        'text-emphasis-color',
        'var(--wp--preset--color--primary)'
    ),
    'styleRemoved' => $block->removeProperty($annotation, 'text-emphasis-style'),
    'emphasisRemoved' => $block->removeProperty($annotation, 'text-emphasis'),
];

$expected = [
    'emphasisStyle' => [
        'value' => 'dot',
        'important' => false,
    ],
    'primaryEmphasis' => 'text-emphasis: dot var(--wp--preset--color--primary); text-emphasis-position: over; color: var(--wp--preset--color--contrast)',
    'styleRemoved' => 'text-emphasis-color: var(--wp--preset--color--accent); text-emphasis-position: over; color: var(--wp--preset--color--contrast)',
    'emphasisRemoved' => 'text-emphasis-position: over; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text-emphasis CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
