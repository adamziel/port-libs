<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValue = [
    'queue' => [
        ['hook' => 'sync_plugins', 'priority' => 20],
        ['hook' => 'refresh_theme', 'priority' => 30],
    ],
    'meta' => [],
];

$jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($optionValue));
$afterInsert = SQLiteJsonArrayInsert::arrayInsertSqlFunction(
    'jsonb_array_insert',
    $jsonb,
    '$.queue[#-1]',
    new SQLiteBlobValue(SQLiteJsonB::encode(['hook' => 'pre_refresh', 'priority' => 25])),
);
$afterSet = SQLiteJsonMutation::mutateSqlFunction(
    'jsonb_set',
    $afterInsert,
    '$.meta.lastMutation',
    'jsonb-current-path-next16',
    '$.queue[#]',
    new SQLiteBlobValue(SQLiteJsonB::encode(['hook' => 'cleanup', 'priority' => 40])),
);

echo json_encode([
    'option_name' => 'active_plugins',
    'resultKind' => 'sqlite-jsonb',
    'decodedAfter' => SQLiteJsonB::decode($afterSet->bytes),
    'applicationUse' => 'Applies SQLite jsonb_array_insert/jsonb_set current-index paths to copied wp_options JSONB queues, preserving pre-last insertion, append, and nested metadata creation without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
