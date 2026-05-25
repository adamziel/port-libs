<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPretty;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValues = [
    'strict_settings' => '{"plugin":{"enabled":true,"modes":["cache","seo"],"limits":{"batch":25}}}',
    'json5_settings' => "{plugin:{enabled:true,modes:['cache','seo',],}, /* copied option */}",
    'custom_indent_settings' => '{"plugin":{"enabled":true,"modes":["cache","seo"]}}',
    'boolean_indent_settings' => '{"plugin":{"enabled":true,"source":"boolean-indent"}}',
    'float_indent_settings' => '{"plugin":{"enabled":true,"source":"float-indent"}}',
    'text_blob_settings' => new SQLiteBlobValue('{"plugin":{"enabled":true,"source":"cast-blob"}}'),
    'jsonb_settings' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => true,
            'modes' => ['cache', 'seo'],
        ],
    ])),
    'scalar_flag_settings' => true,
    'scalar_disabled_settings' => false,
    'scalar_retry_budget' => -7,
    'scalar_ratio_settings' => 3.5,
    'scalar_backoff_ratio' => 0.125,
    'scalar_whole_real_settings' => 3.0,
    'null_settings' => null,
    'malformed_settings' => '{plugin:true,,}',
];

$report = [];
foreach ($optionValues as $optionName => $optionValue) {
    $indent = match ($optionName) {
        'custom_indent_settings' => "\t",
        'boolean_indent_settings' => true,
        'float_indent_settings' => 2.5,
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
    'wordpressUse' => 'Local-only wp_options JSON review output that mirrors SQLite json_pretty() for strict JSON text, JSON5 plugin settings, custom text/numeric/boolean indentation, cast text BLOBs, JSONB option blobs, scalar SQL option values including whole REAL values, NULL option values, and malformed settings before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
