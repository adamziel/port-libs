<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$typographyRules = 'tab-size: +004; word-spacing: NORMAL; letter-spacing: 0.500EM; text-indent: each-line hanging 3.00em; --wp--custom--indent: each-line hanging 3.00em';

$actual = [
    'tabSize' => $block->getProperty($typographyRules, 'tab-size'),
    'editorSpacing' => $block->setProperty($typographyRules, 'word-spacing', '+3.00PX', true),
    'normalizedIndent' => $block->setProperty($typographyRules, 'text-indent', 'hanging 2.500em each-line'),
    'dropLetterSpacing' => $block->removeProperty($typographyRules, 'letter-spacing'),
    'customPreserved' => $block->getProperty($typographyRules, '--wp--custom--indent'),
];

$expected = [
    'tabSize' => ['value' => '4', 'important' => false],
    'editorSpacing' => 'tab-size: 4; letter-spacing: .5em; text-indent: 3em hanging each-line; --wp--custom--indent: each-line hanging 3.00em; word-spacing: 3px !important',
    'normalizedIndent' => 'tab-size: 4; word-spacing: normal; letter-spacing: .5em; text-indent: 2.5em hanging each-line; --wp--custom--indent: each-line hanging 3.00em',
    'dropLetterSpacing' => 'tab-size: 4; word-spacing: normal; text-indent: 3em hanging each-line; --wp--custom--indent: each-line hanging 3.00em',
    'customPreserved' => ['value' => 'each-line hanging 3.00em', 'important' => false],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text spacing CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
