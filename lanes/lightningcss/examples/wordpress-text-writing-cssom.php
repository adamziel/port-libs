<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$textRules = 'text-transform: UpperCase full-size-kana full-width; white-space: Pre-Wrap; hyphens: Manual; text-align: Match-Parent; unicode-bidi: Isolate-Override; text-size-adjust: 100.0%; -webkit-text-size-adjust: NONE; marker-side: Match-Parent; color: var(--wp--preset--color--contrast)';

$actual = [
    'transform' => $block->getProperty($textRules, 'text-transform'),
    'whiteSpace' => $block->getProperty($textRules, 'white-space'),
    'hyphenation' => $block->getProperty($textRules, 'hyphens'),
    'bidi' => $block->getProperty($textRules, 'unicode-bidi'),
    'mobileAdjust' => $block->getProperty($textRules, '-webkit-text-size-adjust'),
    'editorTransform' => $block->setProperty($textRules, 'text-transform', 'Capitalize full-width'),
    'justifyText' => $block->setProperty($textRules, 'text-align', 'Justify-All'),
    'webkitAdjust' => $block->setProperty($textRules, '-webkit-text-size-adjust', 'Auto'),
    'withoutBidi' => $block->removeProperty($textRules, 'unicode-bidi'),
];

$expected = [
    'transform' => ['value' => 'uppercase full-width full-size-kana', 'important' => false],
    'whiteSpace' => ['value' => 'pre-wrap', 'important' => false],
    'hyphenation' => ['value' => 'manual', 'important' => false],
    'bidi' => ['value' => 'isolate-override', 'important' => false],
    'mobileAdjust' => ['value' => 'none', 'important' => false],
    'editorTransform' => 'text-transform: capitalize full-width; white-space: pre-wrap; hyphens: manual; text-align: match-parent; unicode-bidi: isolate-override; text-size-adjust: 100%; -webkit-text-size-adjust: none; marker-side: match-parent; color: var(--wp--preset--color--contrast)',
    'justifyText' => 'text-transform: uppercase full-width full-size-kana; white-space: pre-wrap; hyphens: manual; text-align: justify-all; unicode-bidi: isolate-override; text-size-adjust: 100%; -webkit-text-size-adjust: none; marker-side: match-parent; color: var(--wp--preset--color--contrast)',
    'webkitAdjust' => 'text-transform: uppercase full-width full-size-kana; white-space: pre-wrap; hyphens: manual; text-align: match-parent; unicode-bidi: isolate-override; text-size-adjust: 100%; -webkit-text-size-adjust: auto; marker-side: match-parent; color: var(--wp--preset--color--contrast)',
    'withoutBidi' => 'text-transform: uppercase full-width full-size-kana; white-space: pre-wrap; hyphens: manual; text-align: match-parent; text-size-adjust: 100%; -webkit-text-size-adjust: none; marker-side: match-parent; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text/writing CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
