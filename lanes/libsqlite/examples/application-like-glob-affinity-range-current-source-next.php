<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 1, 'option_name' => 'wp_plugin_alpha', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'wp_plugin_beta', 'option_value' => 'plugin:beta'],
    ['option_id' => 3, 'option_name' => 'wp_option_42', 'option_value' => 42],
    ['option_id' => 4, 'option_name' => 'wp_plugin_literal', 'option_value' => 'plugin:%literal'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'wp_plugin_alpha', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'wp_plugin_beta', 'option_value' => 'plugin:beta2'],
    ['option_id' => 3, 'option_name' => 'wp_option_42', 'option_value' => '42'],
    ['option_id' => 4, 'option_name' => 'wp_plugin_literal', 'option_value' => 'plugin:%literal'],
    ['option_id' => 5, 'option_name' => 'wp_plugin_new', 'option_value' => 'plugin:fresh'],
];

echo json_encode([
    'likePluginPrefix' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::optionRowValuePlan(
        $currentRows,
        $nextRows,
        'option_value',
        'plugin:%',
        'LIKE',
        'TEXT',
        'BINARY',
        null,
        true,
    ),
    'globPluginPrefix' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::optionRowValuePlan(
        $currentRows,
        $nextRows,
        'option_value',
        'plugin:*',
        'GLOB',
        'TEXT',
        'BINARY',
    ),
    'numericTextAffinity' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::optionRowValuePlan(
        $currentRows,
        $nextRows,
        'option_value',
        '4%',
        'LIKE',
        'NUMERIC',
        'BINARY',
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
