<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'widget_recent-posts', 'kind' => 'widget'],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan = SQLiteSkipScanStat4PartialOrderPlan::plan(
    $rows,
    'idx_wp_options_autoload_plugin_name_stat4',
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
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 2, 'nDLt' => 1],
        ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 4, 'nDLt' => 1],
        ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 0, 'nDLt' => 0],
    ],
    [['column' => 'option_name', 'direction' => 'DESC']],
    true,
    'NOCASE',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'usable');
    assert($plan['rowids'] === [1, 2, 3, 5, 6, 8]);
    assert($plan['orderByMode'] === 'partial-current-next');
    assert($plan['stat4CurrentNextByPrefix'][0]['current']['suffix'] === 'plugin_alpha');
    assert($plan['stat4CurrentNextByPrefix'][0]['next']['suffix'] === 'plugin_beta');
    echo "application-planner-stat4-partial-skipscan-order-current-next52 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-planner-stat4-partial-skipscan-order-current-next52',
    'status' => $plan['status'],
    'index' => $plan['indexName'],
    'rowids' => $plan['rowids'],
    'estimatedRows' => $plan['estimatedRows'],
    'estimatedCost' => $plan['estimatedCost'],
    'orderByMode' => $plan['orderByMode'],
    'reverseScan' => $plan['reverseScan'],
    'sortBlockCount' => $plan['sortBlockCount'],
    'stat4CurrentNextByPrefix' => $plan['stat4CurrentNextByPrefix'],
    'applicationUse' => 'Copied wp_options plugin-option scans can use STAT4 current/next evidence for a partial skip-scan index while recognizing suffix-only ORDER BY as a per-prefix block sort, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
