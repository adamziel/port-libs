<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteSequenceRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-sequence-counters.php path/to/application.sqlite [table[,table...]]\n");
    exit(1);
}

$tables = isset($argv[2])
    ? array_values(array_filter(array_map(trim(...), explode(',', $argv[2])), static fn (string $table): bool => $table !== ''))
    : ['wp_posts', 'wp_comments', 'wp_users'];

$database = SQLiteDatabase::fromFile($databasePath);
$records = array_map(
    static fn (SQLiteSequenceRecord $record): array => $record->toArray(),
    $database->sqliteSequenceRecords(),
);
$selected = [];
foreach ($tables as $table) {
    $record = $database->sqliteSequenceForTable($table);
    $selected[$table] = $record?->toArray();
}

echo json_encode([
    'path' => $databasePath,
    'sqlite_sequence' => [
        'records' => $records,
        'selectedTables' => $selected,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
