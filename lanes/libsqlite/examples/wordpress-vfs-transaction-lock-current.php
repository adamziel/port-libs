<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteVfsTransactionLockPlan;

$database = '/srv/www/wp-content/database/.ht.sqlite';
$locks = new SQLiteVfsLockState();

$readerBegin = SQLiteVfsTransactionLockPlan::begin($locks, $database, 'wp-cli-export', 'BEGIN');
$readerRead = SQLiteVfsTransactionLockPlan::firstRead($locks, $database, 'wp-cli-export', $readerBegin['begin']);
$writerBegin = SQLiteVfsTransactionLockPlan::begin($locks, $database, 'wp-admin-import', 'BEGIN IMMEDIATE TRANSACTION');
$commitBlocked = SQLiteVfsTransactionLockPlan::promoteForCommit($locks, $database, 'wp-admin-import', $writerBegin['begin']);
$readerDone = SQLiteVfsTransactionLockPlan::finish($locks, $database, 'wp-cli-export', $readerBegin['begin']);
$commitReady = SQLiteVfsTransactionLockPlan::promoteForCommit($locks, $database, 'wp-admin-import', $writerBegin['begin']);
$writerDone = SQLiteVfsTransactionLockPlan::finish($locks, $database, 'wp-admin-import', $writerBegin['begin']);

$summary = [
    'scenario' => 'wordpress-vfs-transaction-lock-current',
    'wordpressUse' => 'Coordinate copied WordPress SQLite import transactions with SQLite-style VFS lock transitions: deferred readers take shared locks on first access, BEGIN IMMEDIATE reserves the single writer slot, commit waits for readers before exclusive rollback-journal writes, and locks are released after commit or rollback.',
    'databasePath' => $database,
    'reader' => [
        'beginStatus' => $readerBegin['status'],
        'firstReadStatus' => $readerRead['status'],
        'held' => $readerRead['held'],
        'finishStatus' => $readerDone['status'],
    ],
    'writer' => [
        'beginStatus' => $writerBegin['status'],
        'beginHeld' => $writerBegin['held'],
        'blockedCommitStatus' => $commitBlocked['status'],
        'blockedCommitReason' => $commitBlocked['reason'],
        'readyCommitStatus' => $commitReady['status'],
        'readyCommitHeld' => $commitReady['held'],
        'finishStatus' => $writerDone['status'],
        'remainingLocks' => $locks->snapshot(),
        'dependencies' => $writerDone['dependencies'],
    ],
    'dependencyClosure' => 'no new support component needed; this composes the existing native PHP BEGIN lock planner, byte-range lock plan, and VFS lock-state application',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['reader']['firstReadStatus'] === 'read_lock_acquired');
    assert($summary['writer']['beginHeld'] === 'reserved');
    assert($summary['writer']['blockedCommitStatus'] === 'blocked');
    assert($summary['writer']['readyCommitStatus'] === 'commit_lock_acquired');
    assert($summary['writer']['readyCommitHeld'] === 'exclusive');
    assert($summary['writer']['remainingLocks'] === []);
    echo "wordpress-vfs-transaction-lock-current self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
