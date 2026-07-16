<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteStat4SkipScanOrderCurrentSourcePlan;

$prepared = [
    'name' => 'main-prepared',
    'schemaCookie' => 21,
    'stat4Generation' => 8,
    'indexName' => 'idx_wp_options_autoload_name_stat4',
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'lower' => 'plugin_',
    'upper' => 'plugin_zzzz',
    'collation' => 'NOCASE',
    'partialPredicate' => [
        ['column' => 'kind', 'operator' => '=', 'value' => 'plugin'],
        ['column' => 'option_name', 'operator' => '>=', 'value' => 'plugin_'],
    ],
    'queryTerms' => [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
    ],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = $prepared;
$current['name'] = 'main-after-analyze';
$current['schemaCookie'] = 22;
$current['stat4Generation'] = 9;
$current['rows'][] = ['rowid' => 4, 'autoload' => 'auto', 'option_name' => 'plugin_cache', 'kind' => 'plugin'];
$current['rows'][] = ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'plugin_zeta', 'kind' => 'plugin'];
$current['stat4Samples'] = [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 1],
];

$plan = SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($prepared, $current, [['column' => 'option_name']]);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['selectedSource'] === 'current');
    assert($plan['reprepareRequired'] === true);
    assert($plan['rowidDelta']['added'] === [4, 5]);
    assert($plan['selectedPlan']['orderByMode'] === 'partial-current-next');
    echo "application-planner-stat4-skipscan-order-current-source-next87 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-planner-stat4-skipscan-order-current-source-next87',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'rowidDelta' => $plan['rowidDelta'],
    'estimatedRowsDelta' => $plan['estimatedRowsDelta'],
    'orderByMode' => $plan['selectedPlan']['orderByMode'],
    'stat4CurrentNextByPrefix' => $plan['selectedPlan']['stat4CurrentNextByPrefix'],
    'detail' => $plan['detail'],
    'applicationUse' => 'Copied wp_options plugin scans must abandon stale STAT4 skip-scan ORDER plans after ANALYZE/schema-cookie changes and replan against the current source before import cleanup queries run.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
