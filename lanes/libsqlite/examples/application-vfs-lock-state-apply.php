<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteOpenPlan;
use PortLibs\LibSqlite\SQLiteVfsLockState;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = '/srv/www/wp-content/database/.ht.sqlite';
$locks = new SQLiteVfsLockState();

$reader = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'shared', false, 'wp-cli-reader', 11));
$writer = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'reserved', false, 'wp-admin-import', 41));
$pending = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'pending', false, 'wp-admin-import'));
$blockedReader = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'shared', false, 'wp-rest-request', 7));
$blockedExclusive = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'exclusive', false, 'wp-admin-import'));
$locks->release($path, 'wp-cli-reader');
$exclusive = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'exclusive', false, 'wp-admin-import'));

$open = SQLiteOpenPlan::forFilename('file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared', true, true);
$openLock = $locks->acquire(SQLiteLockByteRangePlan::forOpenPlan($open, 'shared', 'wp-cron-reader', 13));

echo json_encode([
    'scenario' => 'application-vfs-lock-state-apply',
    'applicationUse' => 'Apply bounded SQLite VFS shared/reserved/pending/exclusive lock state over copied wp_options database opens, preserving reader concurrency, writer exclusivity, pending-reader blocking, and byte-range plan dependencies without requiring ext/sqlite.',
    'databasePath' => $path,
    'reader' => [
        'status' => $reader['status'],
        'held' => $reader['held'],
        'ranges' => $reader['ranges'],
    ],
    'writer' => [
        'status' => $writer['status'],
        'held' => $writer['held'],
        'holders' => $writer['holders'],
    ],
    'pending' => [
        'status' => $pending['status'],
        'held' => $pending['held'],
        'dependencies' => $pending['dependencies'],
    ],
    'blockedReader' => [
        'status' => $blockedReader['status'],
        'reason' => $blockedReader['reason'],
        'blocking' => $blockedReader['blocking'],
    ],
    'exclusiveBeforeReaderDrain' => [
        'status' => $blockedExclusive['status'],
        'reason' => $blockedExclusive['reason'],
        'blocking' => $blockedExclusive['blocking'],
    ],
    'exclusiveAfterReaderDrain' => [
        'status' => $exclusive['status'],
        'held' => $exclusive['held'],
        'holders' => $exclusive['holders'],
    ],
    'openPlanReaderAfterExclusive' => [
        'status' => $openLock['status'],
        'reason' => $openLock['reason'],
        'blocking' => $openLock['blocking'],
    ],
    'snapshot' => $locks->snapshot(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
