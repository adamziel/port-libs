<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValues = [
    'strict_settings' => '{"plugin":{"enabled":true,"modes":["cache","seo"],"limits":{"batch":25}}}',
    'json5_settings' => "{plugin:{enabled:true,modes:['cache','seo',],}, /* copied option */}",
    'custom_indent_settings' => '{"plugin":{"enabled":true,"modes":["cache","seo"]}}',
    'json_subtype_settings' => new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true,"source":"json-subtype"}}'),
    'boolean_indent_settings' => '{"plugin":{"enabled":true,"source":"boolean-indent"}}',
    'false_indent_settings' => '[1,2]',
    'float_indent_settings' => '{"plugin":{"enabled":true,"source":"float-indent"}}',
    'whole_real_indent_settings' => '{"plugin":{"enabled":true,"source":"whole-real-indent"}}',
    'text_blob_settings' => new SQLiteBlobValue('{"plugin":{"enabled":true,"source":"cast-blob"}}'),
    'text_blob_indent_settings' => new SQLiteBlobValue('{"plugin":{"enabled":true,"source":"cast-blob-indent"}}'),
    'jsonb_settings' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => true,
            'modes' => ['cache', 'seo'],
        ],
    ])),
    'jsonb_indent_settings' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => false,
            'source' => 'jsonb-indent',
        ],
    ])),
    'scalar_flag_settings' => true,
    'scalar_disabled_settings' => false,
    'scalar_retry_budget' => -7,
    'scalar_ratio_settings' => 3.5,
    'scalar_backoff_ratio' => 0.125,
    'scalar_whole_real_settings' => 3.0,
    'null_settings' => null,
    'null_indent_settings' => null,
    'malformed_settings' => '{plugin:true,,}',
];

$report = [];
foreach ($optionValues as $optionName => $optionValue) {
    $indent = match ($optionName) {
        'custom_indent_settings' => "\t",
        'boolean_indent_settings' => true,
        'false_indent_settings' => false,
        'float_indent_settings' => 2.5,
        'whole_real_indent_settings' => 3.0,
        'text_blob_indent_settings' => '..',
        'jsonb_indent_settings' => '--',
        'null_indent_settings' => '--',
        default => null,
    };
    try {
        $arguments = [$optionValue];
        if ($indent !== null) {
            $arguments[] = $indent;
        }

        $report[$optionName] = [
            'status' => 'pretty',
            'json' => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', $arguments),
            'directJson' => SQLiteJsonPretty::jsonPrettySqlFunction('JSON_PRETTY', $optionValue, $indent),
        ];
    } catch (InvalidArgumentException $exception) {
        $report[$optionName] = [
            'status' => 'rejected',
            'reason' => $exception->getMessage(),
        ];
    }
}

echo json_encode([
    'optionJson' => $report,
    'wordpressUse' => 'Local-only wp_options JSON review output that mirrors SQLite json_pretty() for strict JSON text, JSON subtype fragments, JSON5 plugin settings, custom text/numeric/boolean indentation, cast text BLOBs, JSONB option blobs with default and custom indentation, scalar SQL option values including whole REAL values, NULL option values, and malformed settings before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
