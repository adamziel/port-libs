<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => 10, 'glob_pattern' => '1*'],
    ['setting_id' => 3, 'key_name' => 'module_alpha', 'key_value' => 'Module_Alpha', 'glob_pattern' => 'Module_*'],
    ['setting_id' => 4, 'key_name' => 'module_latin', 'key_value' => 'module_Éclair', 'glob_pattern' => 'module_[À-ÿ]*'],
    ['setting_id' => 5, 'key_name' => 'module_emoji', 'key_value' => 'module_😀_cache', 'glob_pattern' => 'module_😀*'],
    ['setting_id' => 6, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob'), 'glob_pattern' => 'module_*'],
    ['setting_id' => 7, 'key_name' => 'old_module', 'key_value' => 'module_removed', 'glob_pattern' => 'module_*'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => '10', 'glob_pattern' => '1[0-9]'],
    ['setting_id' => 3, 'key_name' => 'module_alpha', 'key_value' => 'Module_Alpha', 'glob_pattern' => 'Module_[A-Z]*'],
    ['setting_id' => 4, 'key_name' => 'module_latin', 'key_value' => 'module_Éclair', 'glob_pattern' => 'module_[À-ÿ]*'],
    ['setting_id' => 5, 'key_name' => 'module_emoji', 'key_value' => 'module_😀_cache_v2', 'glob_pattern' => 'module_😀*'],
    ['setting_id' => 6, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob'), 'glob_pattern' => 'module_*'],
    ['setting_id' => 8, 'key_name' => 'new_module', 'key_value' => 'module_new', 'glob_pattern' => 'module_*'],
    ['setting_id' => 9, 'key_name' => 'latin_lower_new', 'key_value' => 'module_éclair', 'glob_pattern' => 'module_[À-ÿ]*'],
];

$plan = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan(
    $currentRows,
    $nextRows,
    'key_value',
    'glob_pattern',
    'GLOB',
    null,
    false,
    'UTF-16LE',
    'UTF-16BE',
    'main.app_settings@cookie104',
    'main.app_settings@cookie105',
    104,
    105,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['operator'] === 'GLOB');
    assert($plan['currentRowids'] === [2, 3, 1, 7, 4, 5]);
    assert($plan['nextRowids'] === [2, 3, 1, 8, 4, 9, 5]);
    assert($plan['enteredRowids'] === [8, 9]);
    assert($plan['exitedRowids'] === [7]);
    assert($plan['changedBytesRowids'] === [2, 3, 1, 4, 5]);
    assert($plan['dependencies'][1] === 'sqlite-glob-dynamic-pattern-current-source-next105');
    echo "application-utf16-affinity-glob-current-source-next105 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
