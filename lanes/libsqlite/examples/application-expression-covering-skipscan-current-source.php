<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$preparedRows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'site', 'kind' => 'core'],
];
$currentRows = $preparedRows;
$currentRows[] = ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:4', 'kind' => 'plugin'];

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1320',
    'schemaCookie' => 1320,
    'stat4Generation' => 20,
    'indexName' => 'idx_wp_options_autoload_lower_name_covering_current-source',
    'rootPage' => 72,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name',
    'coveringExpressions' => ['lower(option_name)'],
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
$current['name'] = 'current-main.wp_options@cookie1321';
$current['schemaCookie'] = 1321;
$current['stat4Generation'] = 21;
$current['rootPage'] = 77;
$current['rows'] = $currentRows;
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$plan = SQLiteSkipScanStat4PartialOrderPlan::expressionCoveringSkipScan(
    $prepared,
    $current,
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
        ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
    ],
    [['expression' => 'kind'], ['expression' => 'lower(option_name)']],
    ['option_name', 'option_value'],
    ['lower(option_name)'],
);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['selectedSource'] ?? null) !== 'current') {
        fwrite(STDERR, "expected stale prepared expression skip-scan to reprepare\n");
        exit(1);
    }
    if (($plan['selectedPlan']['expressionCovering'] ?? null) !== true) {
        fwrite(STDERR, "expected lower(option_name) to be covered by the expression index cursor\n");
        exit(1);
    }
    if (($plan['selectedPlan']['tableSeekRequired'] ?? null) !== false) {
        fwrite(STDERR, "expected expression covering cursor to avoid table seek\n");
        exit(1);
    }
    if (($plan['selectedPlan']['rowids'] ?? null) !== [1, 2, 3, 5]) {
        fwrite(STDERR, "expected current source plugin rowids from expression covering skip-scan\n");
        exit(1);
    }

    echo "application-expression-covering-skipscan-current-source self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-expression-covering-skipscan-current-source',
    'selectedSource' => $plan['selectedSource'],
    'rowids' => $plan['selectedPlan']['rowids'],
    'expressionCovering' => $plan['selectedPlan']['expressionCovering'],
    'tableSeekRequired' => $plan['selectedPlan']['tableSeekRequired'],
    'expressionKeys' => array_map(
        static fn (array $pair): mixed => $pair['current']['coveringExpressions']['lower(option_name)'],
        $plan['selectedPlan']['expressionCoveringRows'],
    ),
    'applicationUse' => 'Preview copied wp_options plugin scans where a stale partial skip-scan over lower(option_name) reparses against the current STAT4/index source and projects the expression key from the covering index cursor without table b-tree seeks.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
