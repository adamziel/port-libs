<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-pragma-preflight.php path/to/wordpress.sqlite\n");
    exit(1);
}

$snapshot = SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromFile($databasePath));

echo json_encode([
    'path' => $databasePath,
    'wordpressUse' => 'Preview SQLite PRAGMA metadata needed before copying a WordPress database without requiring the SQLite extension.',
    'pragma' => $snapshot->toArray([
        'page_size',
        'page_count',
        'freelist_count',
        'encoding',
        'journal_mode',
        'auto_vacuum',
        'incremental_vacuum',
        'application_id',
        'user_version',
        'schema_version',
        'data_version',
    ]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
