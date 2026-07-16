<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteOpenPlan;
use PortLibs\LibSqlite\SQLiteVfsFileLock;

$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-file-lock';
$database = '/srv/www/wp-content/database/.ht.sqlite';
$locks = new SQLiteVfsFileLock($root);

$reader = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'shared', false, 'wp-reader', 7));
$writer = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'reserved', false, 'wp-admin'));
$blockedWriter = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'reserved', false, 'wp-cron'));
$pending = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'pending', false, 'wp-admin'));
$blockedReader = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'shared', false, 'wp-rest'));
$locks->release($database, 'wp-reader');
$exclusive = $locks->acquire(SQLiteLockByteRangePlan::forLevel($database, 'exclusive', false, 'wp-admin'));

$open = SQLiteOpenPlan::forFilename('file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared', true, true);
$blockedOpenReader = $locks->acquire(SQLiteLockByteRangePlan::forOpenPlan($open, 'shared', 'wp-cli'));

echo json_encode([
    'scenario' => 'application-vfs-file-lock-apply',
    'reader' => [
        'status' => $reader['status'],
        'lockType' => $reader['lock_type'],
        'lockFile' => $reader['lock_file'],
    ],
    'writer' => [
        'status' => $writer['status'],
        'held' => $writer['held'],
        'lockType' => $writer['lock_type'],
    ],
    'blockedWriter' => [
        'status' => $blockedWriter['status'],
        'reason' => $blockedWriter['reason'],
        'blocking' => $blockedWriter['blocking'],
    ],
    'pending' => [
        'status' => $pending['status'],
        'held' => $pending['held'],
    ],
    'blockedReader' => [
        'status' => $blockedReader['status'],
        'reason' => $blockedReader['reason'],
        'blocking' => $blockedReader['blocking'],
    ],
    'exclusive' => [
        'status' => $exclusive['status'],
        'held' => $exclusive['held'],
        'holders' => $exclusive['holders'],
    ],
    'blockedOpenReader' => [
        'status' => $blockedOpenReader['status'],
        'reason' => $blockedOpenReader['reason'],
    ],
    'dependencies' => $exclusive['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
