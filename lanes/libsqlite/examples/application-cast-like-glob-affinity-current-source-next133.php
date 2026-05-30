<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin:beta'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin:blob')],
    ['option_id' => 4, 'option_name' => 'retry_count', 'option_value' => '42 widgets'],
    ['option_id' => 5, 'option_name' => 'theme', 'option_value' => 'theme:alpha'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin:beta2'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin:blob2')],
    ['option_id' => 4, 'option_name' => 'retry_count', 'option_value' => 42],
    ['option_id' => 6, 'option_name' => 'fresh', 'option_value' => 'plugin:fresh'],
];

$like = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin:%',
    'LIKE',
);
$glob = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows,
    $nextRows,
    'INTEGER',
    '4*',
    'GLOB',
);

$summary = [
    'scenario' => 'application-cast-like-glob-affinity-current-source-next133',
    'applicationUse' => 'Copied wp_options import scans can evaluate CAST(option_value AS ...) through BINARY LIKE/GLOB prefix candidates and residuals while invalidating stale cursors when the next source changes cast text, encoded bytes, or matched rowsets.',
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
