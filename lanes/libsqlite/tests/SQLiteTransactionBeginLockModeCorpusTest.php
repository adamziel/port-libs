<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLiteTransactionBeginLockPlan;

$tests = [];

$exclusivePragma = static function (): SQLitePragmaLockingMode {
    $pragma = new SQLitePragmaLockingMode();
    $pragma->execute('PRAGMA locking_mode=EXCLUSIVE');

    return $pragma;
};

$attachedExclusivePragma = static function (): SQLitePragmaLockingMode {
    $pragma = new SQLitePragmaLockingMode();
    $pragma->execute('PRAGMA wp.locking_mode=exclusive');

    return $pragma;
};

$cases = [
    'plain begin parses as deferred' => static fn (): mixed => SQLiteTransactionBeginLockPlan::parse('BEGIN')['mode'],
    'begin transaction records keyword' => static fn (): mixed => SQLiteTransactionBeginLockPlan::parse('BEGIN TRANSACTION;')['transaction_keyword'],
    'begin deferred normalizes sql' => static fn (): mixed => SQLiteTransactionBeginLockPlan::parse(" begin deferred \n")['normalized_sql'],
    'begin immediate normalizes sql' => static fn (): mixed => SQLiteTransactionBeginLockPlan::parse('BEGIN IMMEDIATE TRANSACTION')['normalized_sql'],
    'begin exclusive normalizes sql' => static fn (): mixed => SQLiteTransactionBeginLockPlan::parse('BEGIN EXCLUSIVE')['normalized_sql'],
    'begin rejects unsupported mode' => static function (): mixed {
        try {
            SQLiteTransactionBeginLockPlan::parse('BEGIN CONCURRENT');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'begin rejects trailing statement' => static function (): mixed {
        try {
            SQLiteTransactionBeginLockPlan::parse('BEGIN; SELECT 1');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'deferred begins without lock in normal mode' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN')['lock_sequence'][0]['level'],
    'deferred reports read lock deferred' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN')['read_lock_deferred'],
    'deferred does not acquire writer' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN')['write_lock_acquired'],
    'immediate reserves writer slot' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE')['lock_sequence'][0]['level'],
    'immediate acquires write lock' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE')['write_lock_acquired'],
    'exclusive uses exclusive rollback lock' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN EXCLUSIVE')['lock_sequence'][0]['level'],
    'exclusive blocks readers reason' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN EXCLUSIVE')['lock_sequence'][0]['reason'],
    'wal exclusive uses reserved lock' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN EXCLUSIVE', journalMode: 'wal')['lock_sequence'][0]['level'],
    'wal exclusive flags immediate parity' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN EXCLUSIVE', journalMode: 'wal')['wal_exclusive_matches_immediate'],
    'locking mode exclusive upgrades deferred' => static function () use ($exclusivePragma): mixed {
        return SQLiteTransactionBeginLockPlan::plan('BEGIN', $exclusivePragma())['lock_sequence'][0]['level'];
    },
    'locking mode exclusive disables deferred read' => static function () use ($exclusivePragma): mixed {
        return SQLiteTransactionBeginLockPlan::plan('BEGIN', $exclusivePragma())['read_lock_deferred'];
    },
    'locking mode exclusive persists after commit flag' => static function () use ($exclusivePragma): mixed {
        return SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', $exclusivePragma())['exclusive_until_disconnect'];
    },
    'temp schema is exclusive' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN', new SQLitePragmaLockingMode(), 'temp')['locking_mode'],
    'temp deferred upgrades to exclusive' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN', new SQLitePragmaLockingMode(), 'temp')['lock_sequence'][0]['level'],
    'attached schema preserves exclusive mode' => static function () use ($attachedExclusivePragma): mixed {
        return SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', $attachedExclusivePragma(), 'wp')['locking_mode'];
    },
    'main schema remains normal while attached exclusive' => static function () use ($attachedExclusivePragma): mixed {
        return SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', $attachedExclusivePragma(), 'main')['locking_mode'];
    },
    'read only deferred is allowed' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN', readOnly: true)['status'],
    'read only immediate is blocked' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', readOnly: true)['status'],
    'read only immediate records block reason' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', readOnly: true)['lock_sequence'][1]['reason'],
    'read only exclusive is blocked' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN EXCLUSIVE', readOnly: true)['status'],
    'memory journal accepted' => static fn (): mixed => SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', journalMode: 'MEMORY')['journal_mode'],
    'invalid journal mode rejected' => static function (): mixed {
        try {
            SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE', journalMode: 'invalid');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'dependencies include begin lock mode' => static fn (): mixed => in_array('sqlite-begin-transaction-lock-mode', SQLiteTransactionBeginLockPlan::plan('BEGIN IMMEDIATE')['dependencies'], true),
];

$expected = [
    'plain begin parses as deferred' => 'deferred',
    'begin transaction records keyword' => true,
    'begin deferred normalizes sql' => 'BEGIN DEFERRED',
    'begin immediate normalizes sql' => 'BEGIN IMMEDIATE TRANSACTION',
    'begin exclusive normalizes sql' => 'BEGIN EXCLUSIVE',
    'begin rejects unsupported mode' => 'rejected',
    'begin rejects trailing statement' => 'rejected',
    'deferred begins without lock in normal mode' => 'none',
    'deferred reports read lock deferred' => true,
    'deferred does not acquire writer' => false,
    'immediate reserves writer slot' => 'reserved',
    'immediate acquires write lock' => true,
    'exclusive uses exclusive rollback lock' => 'exclusive',
    'exclusive blocks readers reason' => 'exclusive transaction blocks readers before first write',
    'wal exclusive uses reserved lock' => 'reserved',
    'wal exclusive flags immediate parity' => true,
    'locking mode exclusive upgrades deferred' => 'exclusive',
    'locking mode exclusive disables deferred read' => false,
    'locking mode exclusive persists after commit flag' => true,
    'temp schema is exclusive' => 'exclusive',
    'temp deferred upgrades to exclusive' => 'exclusive',
    'attached schema preserves exclusive mode' => 'exclusive',
    'main schema remains normal while attached exclusive' => 'normal',
    'read only deferred is allowed' => 'planned',
    'read only immediate is blocked' => 'blocked',
    'read only immediate records block reason' => 'read-only handle cannot start a write transaction',
    'read only exclusive is blocked' => 'blocked',
    'memory journal accepted' => 'memory',
    'invalid journal mode rejected' => 'rejected',
    'dependencies include begin lock mode' => true,
];

foreach ($cases as $name => $callback) {
    $tests['transaction begin lock mode corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
