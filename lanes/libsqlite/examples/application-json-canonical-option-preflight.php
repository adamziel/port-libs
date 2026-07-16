<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValues = [
    'strict_settings' => ' { "plugin" : { "enabled" : true, "modes" : [ "cache" ] } } ',
    'json5_settings' => "{plugin:{enabled:true,modes:['cache','seo',],}, /* copied option */}",
    'text_blob_settings' => new SQLiteBlobValue('{"plugin":{"enabled":true,"source":"cast-blob"}}'),
    'jsonb_settings' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => true,
            'modes' => ['cache', 'seo'],
        ],
    ])),
    'null_settings' => null,
    'malformed_settings' => '{plugin:true,,}',
];

$report = [];
foreach ($optionValues as $optionName => $optionValue) {
    try {
        $jsonb = SQLiteJsonCanonical::jsonSqlFunctionArguments('JSONB', [$optionValue]);
        $report[$optionName] = [
            'status' => 'canonical',
            'json' => SQLiteJsonCanonical::jsonSqlFunctionArguments('JSON', [$optionValue]),
            'jsonbDecoded' => $jsonb instanceof SQLiteBlobValue ? SQLiteJsonB::decode($jsonb->bytes) : null,
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
    'applicationUse' => 'Local-only wp_options JSON canonicalization that mirrors SQLite json(X) and jsonb(X) uppercase argument-vector SQL dispatch for copied strict JSON text, JSON5 plugin settings, cast text BLOBs, JSONB option blobs, NULL option values, and malformed settings before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
