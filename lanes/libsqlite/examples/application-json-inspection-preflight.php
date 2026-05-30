<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonInspection;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonbSettings = SQLiteJsonB::encode([
    'plugin' => [
        'modes' => ['dark', 'light'],
        'enabled' => true,
    ],
]);

$inputs = [
    'strict_settings_text' => '{"plugin":{"modes":["dark","light"],"enabled":true},"mode":"dark"}',
    'json5_settings_text' => "{plugin:{modes:['dark','light',],enabled:true},threshold:.5}",
    'cast_text_blob' => new SQLiteBlobValue('{"plugin":{"modes":["cache","seo"]}}'),
    'jsonb_settings_blob' => new SQLiteBlobValue($jsonbSettings),
    'sql_null_option_value' => null,
];

$checks = [];
foreach ($inputs as $name => $value) {
    $checks[] = [
        'name' => $name,
        'rootType' => SQLiteJsonInspection::jsonType($value),
        'pluginType' => SQLiteJsonInspection::jsonType($value, '$.plugin'),
        'modesType' => SQLiteJsonInspection::jsonType($value, '$.plugin.modes'),
        'modesLength' => SQLiteJsonInspection::jsonArrayLength($value, '$.plugin.modes'),
        'missingLength' => SQLiteJsonInspection::jsonArrayLength($value, '$.plugin.missing'),
        'sqlDispatchPluginType' => SQLiteJsonInspection::inspectionSqlFunction('json_type', $value, '$.plugin'),
        'sqlDispatchModesLength' => SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $value, '$.plugin.modes'),
        'sqlArgumentDispatchPluginType' => SQLiteJsonInspection::inspectionSqlFunctionArguments('JSON_TYPE', [$value, '$.plugin']),
        'sqlArgumentDispatchModesLength' => SQLiteJsonInspection::inspectionSqlFunctionArguments('JSON_ARRAY_LENGTH', [$value, '$.plugin.modes']),
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Local-only wp_options option_value inspection that mirrors SQLite json_type() and json_array_length() direct and argument-vector SQL-dispatch semantics for strict JSON, JSON5 text, cast text BLOBs, JSONB blobs, and SQL NULL before plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
