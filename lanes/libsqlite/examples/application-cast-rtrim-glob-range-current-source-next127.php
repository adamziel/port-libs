<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimGlobRangeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module_cache '],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module_blob ')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => '42 retries'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module_cache'],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 5, 'key_name' => 'fresh_module', 'key_value' => 'module_cache_new'],
];

$plan = SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'module_cache',
);

$summary = [
    'scenario' => 'application-cast-rtrim-glob-range-current-source-next127',
    'applicationUse' => 'Copied app_settings key_value scans can use an RTRIM expression range over CAST(key_value AS TEXT) while retaining a binary GLOB residual so space-padded setting payloads do not become exact GLOB matches during import diffing.',
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
    assert($summary['currentCandidateRowids'] === [1, 2]);
    assert($summary['currentResidualRejectedRowids'] === [2]);
    assert($summary['currentRowids'] === [1]);
    assert($summary['nextRowids'] === [1, 2]);
    assert($summary['enteredRowids'] === [2]);
    assert(in_array('cast-result', $summary['invalidationReasons'], true));
    echo "application-cast-rtrim-glob-range-current-source-next127 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
