<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'text-decoration-skip-ink: ALL; text-emphasis-position: OVER RIGHT; -webkit-text-decoration-skip-ink: None; -webkit-text-emphasis-position: left UNDER; color: var(--wp--preset--color--contrast)';

$actual = [
    'skipInk' => $block->getProperty($declarations, 'text-decoration-skip-ink'),
    'legacySkipInk' => $block->getProperty($declarations, '-webkit-text-decoration-skip-ink'),
    'emphasisPosition' => $block->getProperty($declarations, 'text-emphasis-position'),
    'legacyEmphasisPosition' => $block->getProperty($declarations, '-webkit-text-emphasis-position'),
    'continuousUnderline' => $block->setProperty($declarations, 'text-decoration-skip-ink', 'None'),
    'leftMarks' => $block->setProperty($declarations, 'text-emphasis-position', 'left over'),
    'withoutLegacyPosition' => $block->removeProperty($declarations, '-webkit-text-emphasis-position'),
];

$expected = [
    'skipInk' => [
        'value' => 'all',
        'important' => false,
    ],
    'legacySkipInk' => [
        'value' => 'none',
        'important' => false,
    ],
    'emphasisPosition' => [
        'value' => 'over',
        'important' => false,
    ],
    'legacyEmphasisPosition' => [
        'value' => 'under left',
        'important' => false,
    ],
    'continuousUnderline' => 'text-decoration-skip-ink: none; text-emphasis-position: over; -webkit-text-decoration-skip-ink: none; -webkit-text-emphasis-position: under left; color: var(--wp--preset--color--contrast)',
    'leftMarks' => 'text-decoration-skip-ink: all; text-emphasis-position: over left; -webkit-text-decoration-skip-ink: none; -webkit-text-emphasis-position: under left; color: var(--wp--preset--color--contrast)',
    'withoutLegacyPosition' => 'text-decoration-skip-ink: all; text-emphasis-position: over; -webkit-text-decoration-skip-ink: none; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text decoration skip-ink CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
