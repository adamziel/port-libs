<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteVfsTransactionLockPlan;

$path = '/srv/www/wp-content/database/.ht.sqlite';

return [
    'vfs transaction locks defer begin until first read' => static function (TestRunner $t) use ($path): void {
        $locks = new SQLiteVfsLockState();
        $begin = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-reader', 'BEGIN');
        $t->same('begun', $begin['status']);
        $t->same('begin', $begin['phase']);
        $t->same('deferred', $begin['begin']['mode']);
        $t->same(true, $begin['begin']['read_lock_deferred']);
        $t->same([], $begin['locks']);
        $t->same(null, $begin['held']);
        $t->same([], $begin['holders']);
        $t->same(true, in_array('sqlite-vfs-transaction-lock-current', $begin['dependencies'], true));

        $read = SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-reader', $begin['begin']);
        $t->same('read_lock_acquired', $read['status']);
        $t->same('first_read', $read['phase']);
        $t->same('shared', $read['held']);
        $t->same(['wp-reader' => 'shared'], $read['holders']);
        $t->same('shared', $read['locks'][0]['requested']);
        $t->same(1073741826, $read['locks'][0]['ranges'][0]['offset']);
        $t->same(null, $read['reason']);

        $finish = SQLiteVfsTransactionLockPlan::finish($locks, $path, 'wp-reader', $begin['begin'], 'rollback');
        $t->same('rolled_back', $finish['status']);
        $t->same('rollback', $finish['phase']);
        $t->same(null, $finish['held']);
        $t->same([], $finish['holders']);
        $t->same('released', $finish['locks'][0]['status']);
    },
    'vfs transaction locks reserve one immediate writer while readers continue' => static function (TestRunner $t) use ($path): void {
        $locks = new SQLiteVfsLockState();
        $reader = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-cli-reader', 'BEGIN');
        SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-cli-reader', $reader['begin']);

        $writer = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-admin', 'BEGIN IMMEDIATE TRANSACTION');
        $t->same('begun', $writer['status']);
        $t->same('immediate', $writer['begin']['mode']);
        $t->same('reserved', $writer['held']);
        $t->same([
            'wp-cli-reader' => 'shared',
            'wp-admin' => 'reserved',
        ], $writer['holders']);
        $t->same('reserved', $writer['locks'][0]['requested']);
        $t->same('immediate transaction reserves the writer slot', $writer['locks'][0]['transaction_reason']);

        $lateReader = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-rest-reader', 'BEGIN');
        $lateRead = SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-rest-reader', $lateReader['begin']);
        $t->same('read_lock_acquired', $lateRead['status']);
        $t->same('shared', $lateRead['held']);
        $t->same('reserved', $locks->holders($path)['wp-admin']);

        $secondWriter = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-cron', 'BEGIN IMMEDIATE');
        $t->same('blocked', $secondWriter['status']);
        $t->same(null, $secondWriter['held']);
        $t->same([['connection' => 'wp-admin', 'level' => 'reserved']], $secondWriter['locks'][0]['blocking']);
        $t->same('writer_lock_conflicts_with_existing_writer', $secondWriter['reason']);
    },
    'vfs transaction locks promote rollback journal commits to exclusive after readers drain' => static function (TestRunner $t) use ($path): void {
        $locks = new SQLiteVfsLockState();
        $reader = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-reader', 'BEGIN');
        SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-reader', $reader['begin']);
        $writer = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-admin', 'BEGIN IMMEDIATE');

        $blocked = SQLiteVfsTransactionLockPlan::promoteForCommit($locks, $path, 'wp-admin', $writer['begin']);
        $t->same('blocked', $blocked['status']);
        $t->same('commit_promote', $blocked['phase']);
        $t->same('reserved', $blocked['held']);
        $t->same('exclusive', $blocked['locks'][0]['requested']);
        $t->same([['connection' => 'wp-reader', 'level' => 'shared']], $blocked['locks'][0]['blocking']);
        $t->same('exclusive_lock_waits_for_all_other_holders', $blocked['reason']);

        SQLiteVfsTransactionLockPlan::finish($locks, $path, 'wp-reader', $reader['begin']);
        $promoted = SQLiteVfsTransactionLockPlan::promoteForCommit($locks, $path, 'wp-admin', $writer['begin']);
        $t->same('commit_lock_acquired', $promoted['status']);
        $t->same('exclusive', $promoted['held']);
        $t->same(['wp-admin' => 'exclusive'], $promoted['holders']);
        $t->same('shared', $promoted['locks'][0]['ranges'][2]['name']);

        $commit = SQLiteVfsTransactionLockPlan::finish($locks, $path, 'wp-admin', $writer['begin'], 'commit');
        $t->same('committed', $commit['status']);
        $t->same(null, $commit['held']);
        $t->same([], $commit['holders']);
    },
    'vfs transaction locks treat wal exclusive begin as immediate reservation' => static function (TestRunner $t) use ($path): void {
        $locks = new SQLiteVfsLockState();
        $reader = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-reader', 'BEGIN');
        SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-reader', $reader['begin']);

        $writer = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-admin', 'BEGIN EXCLUSIVE', null, 'main', 'wal');
        $t->same('begun', $writer['status']);
        $t->same('exclusive', $writer['begin']['mode']);
        $t->same('wal', $writer['begin']['journal_mode']);
        $t->same(true, $writer['begin']['wal_exclusive_matches_immediate']);
        $t->same('reserved', $writer['held']);
        $t->same('reserved', $writer['locks'][0]['requested']);
        $t->same([
            'wp-reader' => 'shared',
            'wp-admin' => 'reserved',
        ], $writer['holders']);

        $commitLock = SQLiteVfsTransactionLockPlan::promoteForCommit($locks, $path, 'wp-admin', $writer['begin']);
        $t->same('commit_lock_acquired', $commitLock['status']);
        $t->same('reserved', $commitLock['held']);
        $t->same('reserved', $commitLock['locks'][0]['requested']);
        $t->same([
            'wp-reader' => 'shared',
            'wp-admin' => 'reserved',
        ], $commitLock['holders']);
    },
    'vfs transaction locks honor exclusive locking mode and readonly blockers' => static function (TestRunner $t) use ($path): void {
        $locking = new SQLitePragmaLockingMode();
        $locking->set('exclusive', 'main');
        $locks = new SQLiteVfsLockState();

        $exclusive = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-admin', 'BEGIN', $locking, 'main');
        $t->same('begun', $exclusive['status']);
        $t->same('exclusive', $exclusive['held']);
        $t->same(true, $exclusive['begin']['exclusive_until_disconnect']);
        $t->same('exclusive locking_mode upgrades deferred begin', $exclusive['locks'][0]['transaction_reason']);

        $blockedReader = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-reader', 'BEGIN');
        $blockedRead = SQLiteVfsTransactionLockPlan::firstRead($locks, $path, 'wp-reader', $blockedReader['begin']);
        $t->same('blocked', $blockedRead['status']);
        $t->same([['connection' => 'wp-admin', 'level' => 'exclusive']], $blockedRead['locks'][0]['blocking']);
        $t->same('pending_or_exclusive_lock_blocks_new_reader', $blockedRead['reason']);

        SQLiteVfsTransactionLockPlan::finish($locks, $path, 'wp-admin', $exclusive['begin']);
        $readonly = SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp-readonly', 'BEGIN IMMEDIATE', null, 'main', 'delete', true);
        $t->same('blocked', $readonly['status']);
        $t->same('read-only handle cannot start a write transaction', $readonly['reason']);
        $t->same([], $readonly['holders']);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTransactionLockPlan::begin($locks, '', 'wp', 'BEGIN'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTransactionLockPlan::begin($locks, $path, '', 'BEGIN'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTransactionLockPlan::finish($locks, $path, 'wp', $exclusive['begin'], 'close'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTransactionLockPlan::begin($locks, $path, 'wp', 'BEGIN BOGUS'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteLockByteRangePlan::forLevel($path, 'shared', false, ''));
    },
];
