<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-pragma-preflight.php path/to/application.sqlite\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$snapshot = SQLitePragmaSnapshot::fromDatabase($database);
$schemaCatalog = SQLitePragmaSchemaCatalog::fromDatabase($database);
$lockingMode = new SQLitePragmaLockingMode();
$exclusiveLockingMode = $lockingMode->execute('PRAGMA locking_mode = EXCLUSIVE');
$normalLockingMode = $lockingMode->execute('PRAGMA locking_mode = NORMAL');

echo json_encode([
    'path' => $databasePath,
    'applicationUse' => 'Preview SQLite PRAGMA metadata needed before copying a Application database without requiring the SQLite extension.',
    'pragma' => $snapshot->toArray([
        'page_size',
        'page_count',
        'freelist_count',
        'encoding',
        'journal_mode',
        'locking_mode',
        'auto_vacuum',
        'incremental_vacuum',
        'application_id',
        'user_version',
        'schema_version',
        'data_version',
    ]),
    'lockingMode' => [
        'initial' => (new SQLitePragmaLockingMode())->execute('PRAGMA locking_mode'),
        'exclusive' => $exclusiveLockingMode,
        'normal' => $normalLockingMode,
        'temp' => $lockingMode->execute('PRAGMA temp.locking_mode'),
    ],
    'schemaPragmas' => [
        'table_info' => $schemaCatalog->execute('PRAGMA table_info(wp_options)')['rows'],
        'table_xinfo' => $schemaCatalog->execute('PRAGMA table_xinfo(wp_options)')['rows'],
        'index_list' => $schemaCatalog->execute('PRAGMA index_list(wp_options)')['rows'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
