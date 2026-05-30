<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache '],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_blob ')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => '42 retries'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache'],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_blob')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 5, 'key_name' => 'fresh_plugin', 'key_value' => 'plugin_cache_new'],
];

$plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin\\_cache',
    '\\',
);

$summary = [
    'scenario' => 'application-cast-rtrim-like-current-source-next131',
    'applicationUse' => 'Copied app_settings key_value scans can use an RTRIM expression range over CAST(key_value AS TEXT) while retaining the LIKE residual, so space-padded option payloads remain candidates but do not become exact LIKE matches during import diffing.',
    'range' => $plan['range'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['range'] === ['lowerInclusive' => 'plugin_cache', 'upperBound' => 'plugin_cachf']);
    assert($summary['currentCandidateRowids'] === [1, 2]);
    assert($summary['currentResidualRejectedRowids'] === [2]);
    assert($summary['currentRowids'] === [1]);
    assert($summary['nextRowids'] === [1, 2]);
    assert($summary['enteredRowids'] === [2]);
    assert(in_array('matched-rowset', $summary['invalidationReasons'], true));
    echo "application-cast-rtrim-like-current-source-next131 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
