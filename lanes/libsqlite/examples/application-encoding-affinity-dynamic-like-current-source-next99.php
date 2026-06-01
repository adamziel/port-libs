<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeGlobAffinityCurrentSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'plugin_percent', 'key_value' => 'plugin_100%_enabled', 'like_pattern' => 'plugin!_100!%%', 'like_escape' => '!'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => 10, 'like_pattern' => '1%', 'like_escape' => null],
    ['setting_id' => 3, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin_blob'), 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['setting_id' => 4, 'key_name' => 'theme_alpha', 'key_value' => 'theme_alpha', 'like_pattern' => 'theme%', 'like_escape' => null],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'plugin_percent', 'key_value' => 'plugin_100%_enabled', 'like_pattern' => 'plugin#_100#%%', 'like_escape' => '#'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => '10', 'like_pattern' => '1%', 'like_escape' => null],
    ['setting_id' => 4, 'key_name' => 'theme_alpha', 'key_value' => 'theme_alpha', 'like_pattern' => 'theme%', 'like_escape' => null],
    ['setting_id' => 5, 'key_name' => 'plugin_new', 'key_value' => 'plugin_new', 'like_pattern' => 'plugin%', 'like_escape' => null],
];

$plan = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan(
    $currentRows,
    $nextRows,
    'key_value',
    'like_pattern',
    'like_escape',
    false,
    'UTF-16LE',
    'UTF-16BE',
    'main.app_settings',
    'main.app_settings',
    99,
    100,
);

$summary = [
    'scenario' => 'application-encoding-affinity-dynamic-like-current-source-next99',
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'changedPatternRowids' => $plan['changedPatternRowids'],
    'changedStorageRowids' => $plan['changedStorageRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['currentRowids'] === [2, 1, 4]);
    assert($summary['nextRowids'] === [2, 5, 1, 4]);
    assert($summary['changedPatternRowids'] === [1]);
    assert($summary['changedStorageRowids'] === [2]);
    assert($summary['enteredRowids'] === [5]);
    assert(in_array('pattern-affinity', $summary['invalidationReasons'], true));
    echo "application-encoding-affinity-dynamic-like-current-source-next99 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
