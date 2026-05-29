<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastCollationLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 10, 'option_name' => 'plugin_rate', 'option_value' => '4.5ms', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:blob  '), 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN:CACHE', 'autoload' => 'yes'],
];

$nextRows = [
    ['option_id' => 10, 'option_name' => 'plugin_rate', 'option_value' => '5.5ms', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:blob'), 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN:CACHE', 'autoload' => 'yes'],
    ['option_id' => 13, 'option_name' => 'plugin_added', 'option_value' => '49', 'autoload' => 'yes'],
];

$plan = SQLiteCastCollationLikeCurrentSourceNextPlan::wordpressOptionValueCastScan(
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
