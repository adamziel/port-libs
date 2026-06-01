<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module:alpha'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module:beta'],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module:blob')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => '42 widgets'],
    ['setting_id' => 5, 'key_name' => 'bundle', 'key_value' => 'bundle:alpha'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module:alpha'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module:beta2'],
    ['setting_id' => 3, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module:blob2')],
    ['setting_id' => 4, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 6, 'key_name' => 'fresh', 'key_value' => 'module:fresh'],
];

$like = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'module:%',
    'LIKE',
);
$glob = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'INTEGER',
    '4*',
    'GLOB',
);

$summary = [
    'scenario' => 'application-cast-like-glob-affinity-current-source-next133',
    'applicationUse' => 'Copied app_settings import scans can evaluate CAST(key_value AS ...) through BINARY LIKE/GLOB prefix candidates and residuals while invalidating stale cursors when the next source changes cast text, encoded bytes, or matched rowsets.',
    'likeCurrentRowids' => $like['currentRowids'],
    'likeNextRowids' => $like['nextRowids'],
    'likeChangedTextRowids' => $like['changedTextRowids'],
    'likeChangedBytesRowids' => $like['changedBytesRowids'],
    'likeInvalidationReasons' => $like['invalidationReasons'],
    'globIntegerCurrentRowids' => $glob['currentRowids'],
    'globIntegerNextRowids' => $glob['nextRowids'],
    'dependencies' => $like['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['likeCurrentRowids'] === [1, 2, 3]);
    assert($summary['likeNextRowids'] === [1, 2, 3, 6]);
    assert($summary['likeChangedTextRowids'] === [2, 3, 4, 5, 6]);
    assert($summary['likeInvalidationReasons'] === ['source-name', 'schema-cookie', 'cast-result', 'text-affinity', 'encoded-bytes', 'candidate-rowset', 'matched-rowset']);
    assert($summary['globIntegerCurrentRowids'] === [4]);
    assert($summary['globIntegerNextRowids'] === [4]);
    echo "application-cast-like-glob-affinity-current-source-next133 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
