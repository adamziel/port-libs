<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = '/srv/www/wp-content/database/.ht.sqlite';

$reader = SQLiteLockByteRangePlan::transition($path, 'none', 'shared', false, 'wp-cli-reader', 11);
$writer = SQLiteLockByteRangePlan::transition($path, 'shared', 'reserved', false, 'wp-admin-import', 11);
$pending = SQLiteLockByteRangePlan::transition($path, 'reserved', 'pending', false, 'wp-admin-import', 11);
$exclusive = SQLiteLockByteRangePlan::transition($path, 'pending', 'exclusive', false, 'wp-admin-import', 11);
$release = SQLiteLockByteRangePlan::transition($path, 'exclusive', 'none', false, null, 11);
$nolock = SQLiteLockByteRangePlan::transition($path, 'none', 'shared', true, 'repair-copy', 11);

echo json_encode([
    'scenario' => 'application-vfs-lock-byte-range-current-next72',
    'applicationUse' => 'Plan SQLite VFS byte-range lock current/next transitions for copied Application database readers and writers, showing retained, newly acquired, and released lock bytes before native VFS application without requiring ext/sqlite.',
    'databasePath' => $path,
    'reader' => [
        'status' => $reader['status'],
        'acquire' => $reader['acquire'],
    ],
    'writerReserved' => [
        'retain' => $writer['retain'],
        'acquire' => $writer['acquire'],
        'release' => $writer['release'],
    ],
    'writerPending' => [
        'retain' => $pending['retain'],
        'acquire' => $pending['acquire'],
        'release' => $pending['release'],
    ],
    'writerExclusive' => [
        'retain' => $exclusive['retain'],
        'acquire' => $exclusive['acquire'],
        'release' => $exclusive['release'],
    ],
    'release' => [
        'status' => $release['status'],
        'release' => $release['release'],
    ],
    'nolockReader' => [
        'status' => $nolock['status'],
        'reason' => $nolock['reason'],
        'dependencies' => $nolock['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
