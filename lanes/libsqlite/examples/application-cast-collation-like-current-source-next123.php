<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastCollationLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['setting_id' => 10, 'key_name' => 'plugin_rate', 'key_value' => '4.5ms', 'load_policy' => 'yes'],
    ['setting_id' => 11, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin:blob  '), 'load_policy' => 'yes'],
    ['setting_id' => 12, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN:CACHE', 'load_policy' => 'yes'],
];

$nextRows = [
    ['setting_id' => 10, 'key_name' => 'plugin_rate', 'key_value' => '5.5ms', 'load_policy' => 'yes'],
    ['setting_id' => 11, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin:blob'), 'load_policy' => 'yes'],
    ['setting_id' => 12, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN:CACHE', 'load_policy' => 'yes'],
    ['setting_id' => 13, 'key_name' => 'plugin_added', 'key_value' => '49', 'load_policy' => 'yes'],
];

$plan = SQLiteCastCollationLikeCurrentSourceNextPlan::keyValueRowValueCastScan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin:%',
    'LIKE',
    'NOCASE',
    null,
    false,
    21,
    22,
);

echo json_encode([
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'changedCastRowids' => $plan['changedCastRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
