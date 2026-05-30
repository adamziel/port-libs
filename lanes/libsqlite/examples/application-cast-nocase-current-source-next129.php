<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastNocaseCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'Plugin_Cache'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('PLUGIN_CACHE_BLOB')],
    ['option_id' => 3, 'option_name' => 'stylesheet', 'option_value' => 'plugin-cache'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin_cache_blob')],
    ['option_id' => 3, 'option_name' => 'stylesheet', 'option_value' => 'plugin-cache'],
    ['option_id' => 4, 'option_name' => 'fresh_plugin', 'option_value' => 'PLUGIN_CACHE_NEW'],
];

$plan = SQLiteCastNocaseCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin\\_cache%',
    '\\',
);

$summary = [
    'scenario' => 'application-cast-nocase-current-source-next129',
    'applicationUse' => 'Copied wp_options import scans can keep a NOCASE LIKE prefix range over CAST(option_value AS TEXT) while retaining the LIKE residual and invalidating stale cursors when the next source changes matching option values.',
    'range' => $plan['range'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'nextCandidateRowids' => $plan['nextCandidateRowids'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedNocaseKeyRowids' => $plan['changedNocaseKeyRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['range'] === ['lowerInclusive' => 'plugin_cache', 'upperBound' => 'plugin_cachf']);
    assert($summary['currentCandidateRowids'] === [1, 2]);
    assert($summary['nextCandidateRowids'] === [1, 2, 4]);
    assert($summary['currentRowids'] === [1, 2]);
    assert($summary['nextRowids'] === [1, 2, 4]);
    assert($summary['enteredRowids'] === [4]);
    assert($summary['changedNocaseKeyRowids'] === [4]);
    assert(in_array('matched-rowset', $summary['invalidationReasons'], true));
    echo "application-cast-nocase-current-source-next129 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
