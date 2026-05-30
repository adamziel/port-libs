<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonValidity;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validJsonb = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'migrations' => ['core', 'cache'],
    ],
]);
$superficialOnlyJsonb = "\x8b\xff" . str_repeat("\0", 7);

$inputs = [
    'strict_plugin_settings_text' => '{"enabled":true,"count":2}',
    'json5_plugin_settings_text' => "{enabled:true, modes:['dark',], /* copied option */}",
    'malformed_plugin_settings_text' => '{enabled:true,,}',
    'cast_strict_text_blob' => new SQLiteBlobValue('{"a":1}'),
    'valid_jsonb_option_blob' => new SQLiteBlobValue($validJsonb),
    'superficial_only_jsonb_blob' => new SQLiteBlobValue($superficialOnlyJsonb),
    'sql_null_option_value' => null,
];

$describeValue = static function (string|SQLiteBlobValue|null $value): array {
    if ($value instanceof SQLiteBlobValue) {
        return [
            'sqliteType' => 'blob',
            'bytes' => strlen($value->bytes),
            'hex' => bin2hex($value->bytes),
        ];
    }
    if ($value === null) {
        return [
            'sqliteType' => 'null',
            'bytes' => null,
            'hex' => null,
        ];
    }

    return [
        'sqliteType' => 'text',
        'bytes' => strlen($value),
        'hex' => null,
    ];
};

$nullableFlagsStatus = static function (string|SQLiteBlobValue|null $value): array {
    try {
        return [
            'ok' => true,
            'value' => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $value, null),
            'error' => null,
        ];
    } catch (InvalidArgumentException $exception) {
        return [
            'ok' => false,
            'value' => null,
            'error' => $exception->getMessage(),
        ];
    }
};

$checks = [];
foreach ($inputs as $name => $value) {
    $checks[] = [
        'name' => $name,
        ...$describeValue($value),
        'jsonValidDefaultStrictText' => SQLiteJsonValidity::jsonValid($value),
        'jsonValidFlag1StrictText' => SQLiteJsonValidity::jsonValid($value, 1),
        'jsonValidFlag2Json5Text' => SQLiteJsonValidity::jsonValid($value, 2),
        'jsonValidFlag4SuperficialJsonb' => SQLiteJsonValidity::jsonValid($value, 4),
        'jsonValidFlag8StrictJsonb' => SQLiteJsonValidity::jsonValid($value, 8),
        'jsonValidFlag6Json5OrSuperficialJsonb' => SQLiteJsonValidity::jsonValid($value, 6),
        'sqlDispatchDefaultStrictText' => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $value),
        'sqlArgumentDispatchDefaultStrictText' => SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$value]),
        'sqlDispatchNullableFlags' => $nullableFlagsStatus($value),
        'sqlDispatchFlag6Json5OrSuperficialJsonb' => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $value, 6),
        'sqlArgumentDispatchFlag6Json5OrSuperficialJsonb' => SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$value, 6]),
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Local-only wp_options option_value preflight for strict JSON text, SQLite JSON5 text, cast text BLOBs, copied JSONB blobs, and uppercase json_valid() argument-vector SQL-dispatch before migration or repair tooling trusts plugin settings.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
