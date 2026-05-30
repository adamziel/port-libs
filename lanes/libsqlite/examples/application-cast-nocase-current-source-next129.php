<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastNocaseCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'Plugin_Cache'],
    ['setting_id' => 2, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('PLUGIN_CACHE_BLOB')],
    ['setting_id' => 3, 'key_name' => 'stylesheet', 'key_value' => 'plugin-cache'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_cache_blob')],
    ['setting_id' => 3, 'key_name' => 'stylesheet', 'key_value' => 'plugin-cache'],
    ['setting_id' => 4, 'key_name' => 'fresh_plugin', 'key_value' => 'PLUGIN_CACHE_NEW'],
];

$plan = SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin\\_cache%',
    '\\',
);

$summary = [
    'scenario' => 'application-cast-nocase-current-source-next129',
    'applicationUse' => 'Copied app_settings import scans can keep a NOCASE LIKE prefix range over CAST(key_value AS TEXT) while retaining the LIKE residual and invalidating stale cursors when the next source changes matching setting values.',
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
