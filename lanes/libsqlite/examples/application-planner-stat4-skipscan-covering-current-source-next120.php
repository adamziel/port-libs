<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows = [
    ['rowid' => 2, 'option_id' => 102, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 1],
    ['rowid' => 3, 'option_id' => 103, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin', 'option_value' => '{"enabled":false}', 'blog_id' => 1],
    ['rowid' => 5, 'option_id' => 105, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 2],
    ['rowid' => 6, 'option_id' => 106, 'autoload' => 'lazy', 'option_name' => 'plugin_zeta', 'kind' => 'plugin', 'option_value' => '{"enabled":false}', 'blog_id' => 2],
    ['rowid' => 9, 'option_id' => 109, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 3],
    ['rowid' => 10, 'option_id' => 110, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 3],
    ['rowid' => 14, 'option_id' => 114, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 4],
    ['rowid' => 15, 'option_id' => 115, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 4],
];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan = SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSourcePlan(
    $rows,
    'idx_wp_options_autoload_name_covering_stat4_next120',
    'autoload',
    'option_name',
    'plugin_',
    'plugin_zzzz',
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
    ],
    [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
        ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'lazy', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
        ['prefix' => 'no', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 5, 'nDLt' => 2],
        ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 5, 'nLt' => 2, 'nDLt' => 1],
        ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 7, 'nDLt' => 2],
    ],
    [['column' => 'option_name']],
    ['autoload', 'option_name', 'option_id', 'option_value', 'blog_id'],
    ['option_id', 'option_name', 'autoload', 'option_value', 'blog_id'],
    true,
    'NOCASE',
);

if ($plan === null || $plan['covering'] !== true) {
    fwrite(STDERR, "No covering STAT4 skip-scan plan selected\n");
    exit(1);
}

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['rowids'] === [2, 3, 5, 6, 9, 10, 14, 15]);
    assert($plan['tableSeekRequired'] === false);
    assert($plan['coveredRowCount'] === 8);
    echo "application-planner-stat4-skipscan-covering-current-source-next120 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-planner-stat4-skipscan-covering-current-source-next120',
    'index' => $plan['indexName'],
    'covering' => $plan['covering'],
    'tableSeekRequired' => $plan['tableSeekRequired'],
    'rowids' => $plan['rowids'],
    'coveredRowCount' => $plan['coveredRowCount'],
    'orderByMode' => $plan['orderByMode'],
    'coveringMode' => $plan['coveringMode'],
    'firstNames' => array_slice(array_map(static fn (array $pair): string => (string) $pair['current']['covering']['option_name'], $plan['currentNextCoveringRows']), 0, 4),
    'applicationUse' => 'Copied wp_options plugin-option scans can combine STAT4 skip-scan current/next evidence with a covering index payload, avoiding table seeks while preserving suffix ORDER BY block-sort evidence.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
