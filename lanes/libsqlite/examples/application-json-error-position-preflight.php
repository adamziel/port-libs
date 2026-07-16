<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validJsonb = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'modes' => ['dark'],
    ],
]);
$superficialOnlyJsonb = "\x8b\xff" . str_repeat("\0", 7);

$inputs = [
    'json5_plugin_settings_text' => "{enabled:true, modes:['dark',], /* copied option */}",
    'duplicate_comma_settings_text' => '{enabled:true,,}',
    'nested_malformed_settings_text' => '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}',
    'leading_zero_number_text' => '{"x":01.5}',
    'cast_strict_text_blob' => new SQLiteBlobValue('{"a":35}'),
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

$checks = [];
foreach ($inputs as $name => $value) {
    $checks[] = [
        'name' => $name,
        ...$describeValue($value),
        'jsonErrorPosition' => SQLiteJsonErrorPosition::jsonErrorPosition($value),
        'sqlDispatchJsonErrorPosition' => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', $value),
        'sqlArgumentDispatchJsonErrorPosition' => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('JSON_ERROR_POSITION', [$value]),
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Local-only wp_options option_value diagnostics that report SQLite json_error_position() style offsets and SQL argument-vector dispatch for JSON5 text, copied text BLOBs, JSONB blobs, and SQL NULL before migration or repair tooling trusts plugin settings.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
