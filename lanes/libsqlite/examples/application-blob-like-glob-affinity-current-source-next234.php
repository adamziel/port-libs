<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBlobValue;

$current = [
    ['setting_id' => 1, 'key_name' => 'service_text', 'key_value' => 'plugin:cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('plugin:cache'), 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'profile_text', 'key_value' => 'theme:cache', 'load_policy' => 'yes'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'service_text', 'key_value' => 'plugin:cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'module_blob', 'key_value' => 'plugin:cache', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'profile_text', 'key_value' => 'theme:cache', 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'module_blob_new', 'key_value' => new SQLiteBlobValue('plugin:new'), 'load_policy' => 'no'],
];

$implicit = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, 'plugin:%');
$cast = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, 'plugin:%', 'LIKE', 'NOCASE', null, false, true);

if (($argv[1] ?? null) === '--self-test') {
    assert($implicit['currentRowids'] === [1]);
    assert($implicit['nextRowids'] === [1, 2]);
    assert($implicit['currentBlobSkippedRowids'] === [2]);
    assert($implicit['nextBlobSkippedRowids'] === [4]);
    assert($cast['currentRowids'] === [1, 2]);
    assert($cast['nextRowids'] === [1, 2, 4]);
    echo "application-blob-like-glob-affinity-current-source-next234 self-test passed\n";
    return;
}

echo json_encode(['implicit' => $implicit, 'explicitCast' => $cast], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
