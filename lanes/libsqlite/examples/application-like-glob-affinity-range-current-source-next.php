<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'extension_alpha', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'extension_beta', 'key_value' => 'plugin:beta'],
    ['setting_id' => 3, 'key_name' => 'setting_42', 'key_value' => 42],
    ['setting_id' => 4, 'key_name' => 'extension_literal', 'key_value' => 'plugin:%literal'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'extension_alpha', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'extension_beta', 'key_value' => 'plugin:beta2'],
    ['setting_id' => 3, 'key_name' => 'setting_42', 'key_value' => '42'],
    ['setting_id' => 4, 'key_name' => 'extension_literal', 'key_value' => 'plugin:%literal'],
    ['setting_id' => 5, 'key_name' => 'extension_new', 'key_value' => 'plugin:fresh'],
];

echo json_encode([
    'likePluginPrefix' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $nextRows,
        'key_value',
        'plugin:%',
        'LIKE',
        'TEXT',
        'BINARY',
        null,
        true,
    ),
    'globPluginPrefix' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $nextRows,
        'key_value',
        'plugin:*',
        'GLOB',
        'TEXT',
        'BINARY',
    ),
    'numericTextAffinity' => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $nextRows,
        'key_value',
        '4%',
        'LIKE',
        'NUMERIC',
        'BINARY',
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
