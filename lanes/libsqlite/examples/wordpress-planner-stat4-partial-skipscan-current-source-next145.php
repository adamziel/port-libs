<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$preparedRows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'plugin_delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_epsilon', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => '_transient_plugin_alpha', 'option_value' => 'tmp', 'kind' => 'transient'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:6', 'kind' => 'plugin'],
];
$currentRows = $preparedRows;
$currentRows[] = ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
$currentRows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

$preparedStat4 = [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];
$currentStat4 = $preparedStat4;
$currentStat4[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];
$currentStat4[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

$source = static fn (string $name, int $cookie, int $stat4Generation, array $rows, array $samples): array => [
    'name' => $name,
    'schemaCookie' => $cookie,
    'stat4Generation' => $stat4Generation,
    'indexName' => 'idx_wp_options_autoload_lower_name_partial_next145',
    'rootPage' => $cookie + 100,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zzzz',
    'upperInclusive' => true,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => $rows,
    'stat4Samples' => $samples,
];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$plan = SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan::materializeNext145(
    $source('prepared-main.wp_options@cookie1450', 1450, 45, $preparedRows, $preparedStat4),
    $source('current-main.wp_options@cookie1451', 1451, 46, $currentRows, $currentStat4),
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => '__expr_lower_option_name']],
        ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
    ],
    [['expression' => 'kind'], ['expression' => 'lower(option_name)']],
    ['option_name', 'option_value'],
);

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'rowids' => $plan['selectedPlan']['rowids'] ?? [],
    'prefixes' => array_column($plan['prefixProgram'], 'prefix'),
    'payloadRowCount' => count($plan['payloadRows']),
    'firstPayload' => $plan['payloadRows'][0]['payload'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
