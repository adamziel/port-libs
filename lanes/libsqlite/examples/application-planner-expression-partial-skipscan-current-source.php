<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexSkipScanPlan.php';
require_once __DIR__ . '/../src/SQLiteSkipScanStat4PartialOrderPlan.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1410',
    'schemaCookie' => 1410,
    'stat4Generation' => 41,
    'indexName' => 'idx_wp_options_autoload_lower_name_partial_current-source',
    'rootPage' => 14101,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zzzz',
    'upperInclusive' => true,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'lazy', 'option_name' => 'plugin_delta', 'option_value' => 'a:2', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ],
];

$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1411';
$current['schemaCookie'] = 1411;
$current['stat4Generation'] = 42;
$current['rootPage'] = 14111;
$current['rows'][] = ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'PLUGIN_SEO', 'option_value' => 'a:3', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

$next = $current;
$next['name'] = 'next-main.wp_options@cookie1412';
$next['schemaCookie'] = 1412;
$next['stat4Generation'] = 43;
$next['rows'][] = ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'plugin_omega', 'option_value' => 'a:4', 'kind' => 'plugin'];
$next['stat4Samples'][] = ['prefix' => 'yes', 'suffix' => 'plugin_omega', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => '__expr_lower_option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order = [['expression' => 'kind'], ['expression' => 'lower(option_name)']];

$plan = SQLiteSkipScanStat4PartialOrderPlan::expressionPartialSkipScan(
    $prepared,
    $current,
    $partial,
    $query,
    $order,
    ['option_name', 'option_value'],
    $next,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'requires-current-source-reprepare');
    assert($plan['selectedSource'] === 'current');
    assert($plan['nextSourceAdmitted'] === false);
    assert($plan['selectedPlan']['rowids'] === [1, 2, 4]);
    assert($plan['nextSource']['replanReasons'] === ['schema-cookie', 'stat4-generation', 'row-signature', 'stat4-signature']);
    echo "application-planner-expression-partial-skipscan-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-planner-expression-partial-skipscan-current-source',
    'applicationUse' => 'Copied wp_options plugin scans can keep a partial skip-scan over lower(option_name) only for the selected current source, while rejecting a next source whose expression keys and STAT4 samples changed before the prepared cursor is reused.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'rowids' => $plan['selectedPlan']['rowids'],
    'nextSourceAdmitted' => $plan['nextSourceAdmitted'],
    'replanReasons' => $plan['nextSource']['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
