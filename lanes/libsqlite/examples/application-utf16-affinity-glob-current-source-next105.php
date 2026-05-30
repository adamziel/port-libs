<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['option_id' => 2, 'option_name' => 'retry_count', 'option_value' => 10, 'glob_pattern' => '1*'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'option_value' => 'Plugin_Alpha', 'glob_pattern' => 'Plugin_*'],
    ['option_id' => 4, 'option_name' => 'plugin_latin', 'option_value' => 'plugin_Éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
    ['option_id' => 5, 'option_name' => 'plugin_emoji', 'option_value' => 'plugin_😀_cache', 'glob_pattern' => 'plugin_😀*'],
    ['option_id' => 6, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'glob_pattern' => 'plugin_*'],
    ['option_id' => 7, 'option_name' => 'old_plugin', 'option_value' => 'plugin_removed', 'glob_pattern' => 'plugin_*'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['option_id' => 2, 'option_name' => 'retry_count', 'option_value' => '10', 'glob_pattern' => '1[0-9]'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'option_value' => 'Plugin_Alpha', 'glob_pattern' => 'Plugin_[A-Z]*'],
    ['option_id' => 4, 'option_name' => 'plugin_latin', 'option_value' => 'plugin_Éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
    ['option_id' => 5, 'option_name' => 'plugin_emoji', 'option_value' => 'plugin_😀_cache_v2', 'glob_pattern' => 'plugin_😀*'],
    ['option_id' => 6, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'glob_pattern' => 'plugin_*'],
    ['option_id' => 8, 'option_name' => 'new_plugin', 'option_value' => 'plugin_new', 'glob_pattern' => 'plugin_*'],
    ['option_id' => 9, 'option_name' => 'latin_lower_new', 'option_value' => 'plugin_éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
];

$plan = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValueDynamicLikeGlobPlan(
    $currentRows,
    $nextRows,
    'option_value',
    'glob_pattern',
    'GLOB',
    null,
    false,
    'UTF-16LE',
    'UTF-16BE',
    'main.wp_options@cookie104',
    'main.wp_options@cookie105',
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
