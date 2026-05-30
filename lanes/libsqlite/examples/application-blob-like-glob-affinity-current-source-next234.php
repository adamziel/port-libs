<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBlobValue;

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_text', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:cache'), 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_text', 'option_value' => 'theme:cache', 'autoload' => 'yes'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_text', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_blob', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_text', 'option_value' => 'theme:cache', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_blob_new', 'option_value' => new SQLiteBlobValue('plugin:new'), 'autoload' => 'no'],
];

$implicit = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($current, $next, 'plugin:%');
$cast = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($current, $next, 'plugin:%', 'LIKE', 'NOCASE', null, false, true);

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
