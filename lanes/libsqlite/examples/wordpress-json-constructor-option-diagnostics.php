<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pluginSettings = new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject(
    'enabled',
    true,
    'modes',
    new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray('cache', 'seo')),
));
$jsonbMigrationQueue = new SQLiteBlobValue(SQLiteJsonB::encode([
    ['step' => 'copy-options', 'ok' => true],
    ['step' => 'rewrite-json', 'ok' => false],
]));

$checks = [
    'option_import_report' => SQLiteJsonConstructor::jsonObject(
        'option_name',
        'plugin_settings',
        'accepted',
        true,
        'settings',
        $pluginSettings,
        'warnings',
        new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray('json5-normalized', 'jsonb-preserved')),
    ),
    'migration_queue' => SQLiteJsonConstructor::jsonArray('queue', $jsonbMigrationQueue, null),
];

try {
    SQLiteJsonConstructor::jsonArray('raw-media-blob', new SQLiteBlobValue("\xab\xcd"));
    $rawBlobStatus = 'accepted';
} catch (InvalidArgumentException $exception) {
    $rawBlobStatus = $exception->getMessage();
}

echo json_encode([
    'checks' => $checks,
    'rawBlobStatus' => $rawBlobStatus,
    'wordpressUse' => 'Local-only wp_options migration diagnostics that mirror SQLite json_array() and json_object() SQL-value construction, JSON subtype passthrough, JSONB BLOB passthrough, and raw BLOB rejection before copied plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
