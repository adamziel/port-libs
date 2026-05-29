<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$preparedRows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'site', 'kind' => 'core'],
];
$currentRows = $preparedRows;
$currentRows[] = ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'option_value' => 'a:4', 'kind' => 'plugin'];

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1270',
    'schemaCookie' => 1270,
    'stat4Generation' => 3,
    'indexName' => 'idx_wp_options_autoload_plugin_covering',
    'rootPage' => 44,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zzzz',
    'upperInclusive' => true,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => $preparedRows,
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
        ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ],
];
$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1271';
$current['schemaCookie'] = 1271;
$current['stat4Generation'] = 4;
$current['rootPage'] = 47;
$current['rows'] = $currentRows;
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);
$query = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
];
$plan = SQLiteSkipScanStat4PartialOrderPlan::partialCoveringSkipScan(
    $prepared,
    $current,
    $partial,
    $query,
    [['expression' => 'kind'], ['expression' => 'option_name']],
    ['option_name', 'option_value'],
);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['selectedSource'] ?? null) !== 'current') {
        fwrite(STDERR, "expected stale prepared statement to reprepare against current source\n");
        exit(1);
    }
    if (($plan['selectedOrder']['constantExpressions'] ?? null) !== ['kind']) {
        fwrite(STDERR, "expected WHERE kind='plugin' to be pruned from ORDER BY\n");
        exit(1);
    }
    if (($plan['selectedPlan']['rowids'] ?? null) !== [1, 2, 3, 5]) {
        fwrite(STDERR, "expected current plugin option rowids from covering partial skip-scan\n");
        exit(1);
    }
    if (($plan['selectedPlan']['tableSeekRequired'] ?? null) !== false) {
        fwrite(STDERR, "expected covering index to avoid table lookup\n");
        exit(1);
    }

    echo "wordpress-planner-partial-covering-skipscan-current-source self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
