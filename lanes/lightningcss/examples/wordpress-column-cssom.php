<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$layout = 'columns: 2 16rem; column-gap: 1.5rem; column-rule: 1px solid #ddd';
$prefixedLayout = '-webkit-columns: 2 16rem; -webkit-column-rule: 1px solid #ddd';

$actual = [
    'columnWidth' => $block->getProperty($layout, 'column-width'),
    'columnCount' => $block->getProperty($layout, 'column-count'),
    'ruleColor' => $block->getProperty($layout, 'column-rule-color'),
    'editorMeasure' => $block->setProperty($layout, 'column-width', '20rem'),
    'themeRuleColor' => $block->setProperty($layout, 'column-rule-color', 'var(--wp--preset--color--contrast)'),
    'dropRuleStyle' => $block->removeProperty($layout, 'column-rule-style'),
    'legacyMeasure' => $block->setProperty($prefixedLayout, '-webkit-column-width', '20rem'),
];

$expected = [
    'columnWidth' => ['value' => '16rem', 'important' => false],
    'columnCount' => ['value' => '2', 'important' => false],
    'ruleColor' => ['value' => '#ddd', 'important' => false],
    'editorMeasure' => 'columns: 2 20rem; column-gap: 1.5rem; column-rule: 1px solid #ddd',
    'themeRuleColor' => 'columns: 2 16rem; column-gap: 1.5rem; column-rule: 1px solid var(--wp--preset--color--contrast)',
    'dropRuleStyle' => 'columns: 2 16rem; column-gap: 1.5rem; column-rule-width: 1px; column-rule-color: #ddd',
    'legacyMeasure' => '-webkit-columns: 2 20rem; -webkit-column-rule: 1px solid #ddd',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected column CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
