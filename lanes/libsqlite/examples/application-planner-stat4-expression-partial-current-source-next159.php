<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('autoload', SQLiteIndexPredicate::EQUALS, 'yes'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$prepared = [
    'name' => 'prepared wp_options',
    'schemaCookie' => 1590,
    'stat4Generation' => 59,
    'indexName' => 'idx_wp_options_lower_name_yes_plugin_next159',
    'rootPage' => 15901,
    'expression' => 'lower(option_name)',
    'expressionColumn' => '__expr_lower_option_name',
    'coveringColumns' => ['option_name', 'autoload'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', '__expr_lower_option_name' => 'plugin_alpha'],
        ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', '__expr_lower_option_name' => 'plugin_beta'],
    ],
    'stat4Samples' => [
        ['key' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0, 'rowid' => 1],
        ['key' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1, 'rowid' => 2],
    ],
];

$current = $prepared;
$current['name'] = 'current copied wp_options';
$current['schemaCookie'] = 1591;
$current['stat4Generation'] = 60;
$current['rootPage'] = 15911;
$current['rows'][] = ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'a:4', '__expr_lower_option_name' => 'plugin_cache'];
$current['rows'][] = ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'a:5', '__expr_lower_option_name' => 'plugin_security'];
$current['stat4Samples'][] = ['key' => 'plugin_cache', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2, 'rowid' => 6];
$current['stat4Samples'][] = ['key' => 'plugin_security', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3, 'rowid' => 7];

$terms = [
    ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => '__expr_lower_option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
    ['operator' => '<', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_t'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4YieldCoveringRows(
    $prepared,
    $current,
    $partial,
    $terms,
    ['option_name', 'option_value'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next159-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['selectedPlan']['rowids'] === [1, 2, 6, 7]);
    assert($plan['yieldProgram'][4]['opcode'] === 'DeferredSeek');
    assert($plan['tableLookupRows'][3]['payload']['option_value'] === 'a:5');
    echo "application-planner-stat4-expression-partial-current-source-next159 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'rowids' => $plan['selectedPlan']['rowids'],
    'stat4Keys' => array_column($plan['selectedPlan']['stat4Samples'], 'key'),
    'tableLookupRequired' => $plan['selectedPlan']['tableLookupRequired'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
