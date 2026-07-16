<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonQuote;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonbSettings = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'count' => 2,
    ],
]);

$inputs = [
    'sql_null_option_value' => null,
    'integer_option_id' => 12345,
    'real_threshold' => 3.14159,
    'exponent_threshold' => 1e2,
    'copied_text_settings' => '{"plugin":{"enabled":true}}',
    'control_character_text' => 'line' . "\n" . 'tab' . "\t" . 'nul' . "\0" . 'end',
    'valid_jsonb_option_blob' => new SQLiteBlobValue($jsonbSettings),
    'raw_blob_option_value' => new SQLiteBlobValue('01234'),
];

$checks = [];
foreach ($inputs as $name => $value) {
    try {
        $quoted = SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', [$value]);
        $status = 'quoted';
    } catch (InvalidArgumentException $exception) {
        $quoted = null;
        $status = $exception->getMessage();
    }

    $checks[] = [
        'name' => $name,
        'sqliteType' => $value instanceof SQLiteBlobValue ? 'blob' : ($value === null ? 'null' : get_debug_type($value)),
        'quotedJson' => $quoted,
        'status' => $status,
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Local-only wp_options option_value preflight that mirrors SQLite json_quote() SQL dispatch for SQL NULL, numeric, text, control-character text, JSONB BLOBs, and raw BLOB rejection before copied settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
