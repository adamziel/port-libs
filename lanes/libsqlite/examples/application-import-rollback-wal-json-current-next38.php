<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$pageSize = 512;
$databaseBytes = str_pad('sqlite-page-1', $pageSize, "\0")
    . str_pad('plugin-before', $pageSize, "\0")
    . str_pad('theme-before', $pageSize, "\0")
    . str_pad('broken-before', $pageSize, "\0");

$walBytes = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, 0x61, 0x62, 0, 0);
for ($index = 1; $index <= 4; $index++) {
    $walBytes .= pack('N*', $index + 1, 0, 0x61, 0x62, 0, 0)
        . str_pad("wal-frame-{$index}", $pageSize, "\0");
}

$plan = SQLiteJsonImportRollbackWalPlan::plan(
    [
        [
            'option_id' => 1,
            'option_name' => 'plugin_settings',
            'option_value' => '{"enabled":false,"version":1}',
            'autoload' => 'yes',
            'page_number' => 2,
        ],
        [
            'option_id' => 2,
            'option_name' => 'theme_mods_twentyfive',
            'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['palette' => ['accent' => 'blue']])),
            'autoload' => 'yes',
            'page_number' => 3,
        ],
        [
            'option_id' => 3,
            'option_name' => 'broken_import_payload',
            'option_value' => '{"enabled":',
            'autoload' => 'no',
            'page_number' => 4,
        ],
    ],
    [
        [
            'statement' => 'enable_plugin',
            'option_name' => 'plugin_settings',
            'function' => 'json_set',
            'path' => '$.enabled',
            'value' => true,
            'wal_frame_index' => 1,
        ],
        [
            'statement' => 'theme_palette',
            'option_name' => 'theme_mods_twentyfive',
            'function' => 'jsonb_set',
            'path' => '$.palette.accent',
            'value' => new SQLiteJsonSubtypeValue('{"slug":"green","contrast":7}'),
            'wal_frame_index' => 2,
        ],
        [
            'statement' => 'broken_payload',
            'option_name' => 'broken_import_payload',
            'function' => 'json_set',
            'path' => '$.enabled',
            'value' => true,
            'wal_frame_index' => 3,
        ],
    ],
    [
        'database_bytes' => $databaseBytes,
        'page_size' => $pageSize,
        'wal_bytes' => $walBytes,
    ]
);

echo json_encode([
    'status' => $plan['status'],
    'failed_statements' => $plan['failed_statements'],
    'database_restored_to_before' => $plan['database_restored_to_before'],
    'wal_frame_count_before' => $plan['wal_frame_count_before'],
    'wal_frame_count_after' => $plan['wal_frame_count_after'],
    'wal_truncated' => $plan['wal_truncated'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
