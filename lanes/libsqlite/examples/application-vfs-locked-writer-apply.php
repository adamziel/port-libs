<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteVfsFileLock;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteVfsLockedFileWriter;

$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-locked-writer-' . bin2hex(random_bytes(4));
$database = '/srv/www/wp-content/database/.ht.sqlite';
$locks = new SQLiteVfsFileLock($root);
$writer = new SQLiteVfsLockedFileWriter(new SQLiteVfsFileWriter($root), $locks);

$reader = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'shared', false, 'wp-reader', 4));
$blocked = $writer->applyExclusive(
    SQLiteLockByteRangePlan::forLevel($database, 'exclusive', false, 'wp-admin-import'),
    [
        ['op' => 'write', 'path' => $database, 'offset' => 0, 'bytes' => 16, 'reason' => 'blocked_import_write'],
    ],
    [$database => 'blocked-wp-bytes'],
    ['application-import-transaction']
);
$blockedLocalFileExists = is_file($root . $database);
$locks->release($database, 'wp-reader');

$applied = $writer->applyExclusive(
    SQLiteLockByteRangePlan::forLevel($database, 'exclusive', false, 'wp-admin-import'),
    [
        ['op' => 'write', 'path' => $database, 'offset' => 0, 'bytes' => 23, 'reason' => 'write_copied_wp_options_page'],
        ['op' => 'sync', 'path' => $database, 'durable' => true, 'reason' => 'sync_copied_wp_options_page'],
        ['op' => 'truncate', 'path' => $database, 'bytes' => 18, 'reason' => 'trim_failed_import_tail'],
        ['op' => 'sync_directory', 'path' => '/srv/www/wp-content/database', 'durable' => true, 'reason' => 'persist_import_directory'],
    ],
    [$database => 'copied-wp-options-bytes'],
    ['application-import-transaction']
);

echo json_encode([
    'scenario' => 'application-vfs-locked-writer-apply',
    'applicationUse' => 'Apply copied wp_options database writes only after an exclusive SQLite VFS process lock is held, then release the lock after durable file and directory sync diagnostics without requiring ext/sqlite.',
    'root' => $root,
    'databasePath' => $database,
    'blockedByReader' => [
        'readerStatus' => $reader['status'],
        'status' => $blocked['status'],
        'reason' => $blocked['reason'],
        'blocking' => $blocked['lock']['blocking'],
        'localFileExists' => $blockedLocalFileExists,
    ],
    'appliedAfterReaderDrain' => [
        'status' => $applied['status'],
        'locked' => $applied['locked'],
        'applied' => $applied['applied'],
        'bytesWritten' => $applied['write']['bytes_written'],
        'bytesTruncated' => $applied['write']['bytes_truncated'],
        'durableSyncs' => $applied['write']['durable_syncs'],
        'directorySyncs' => $applied['write']['directory_syncs'],
        'releaseStatus' => $applied['release']['status'],
        'remainingLocks' => $locks->snapshot(),
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
