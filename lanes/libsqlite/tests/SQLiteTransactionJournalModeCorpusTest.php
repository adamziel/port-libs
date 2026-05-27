<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerJournalOpenPlan;
use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsSyncPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$journalBytes = str_repeat('J', 96);
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$pages = [
    3 => $page('option-value-page-3'),
    1 => $page('option-root-page-1'),
];

$ops = static fn (array $plan): array => array_column($plan['operations'], 'op');
$reasons = static fn (array $plan): array => array_column($plan['operations'], 'reason');

$cases = [
    'delete open close status' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete')['status'],
    'delete open close operation order' => static fn (): mixed => $ops(SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete')),
    'delete open zero header bytes' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete')['operations'][0]['bytes'],
    'delete close journal sidecar path' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete')['operations'][1]['path'],
    'delete close directory sync path' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete')['operations'][2]['path'],
    'truncate open close operation order' => static fn (): mixed => $ops(SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'truncate')),
    'truncate close zero bytes' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'truncate')['operations'][1]['bytes'],
    'persist open close operation order' => static fn (): mixed => $ops(SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'persist')),
    'persist close zero header payload' => static fn (): mixed => strlen(SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'persist')['payloads'][$databasePath . '-journal#persist-zero-header']),
    'persist close reason' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'persist')['operations'][1]['reason'],
    'uppercase journal mode normalizes' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'PERSIST')['journal_mode'],
    'read only transaction open is blocked' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete', readOnly: true)['reason'],
    'immutable transaction open is blocked' => static fn (): mixed => SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'delete', immutable: true)['reason'],
    'null existing journal reports no hot journal' => static fn (): mixed => SQLitePagerJournalOpenPlan::open($databasePath, $pageSize, 'delete')['hot_journal'],
    'empty existing journal is non hot' => static fn (): mixed => SQLitePagerJournalOpenPlan::open($databasePath, $pageSize, 'delete', '')['hot_journal']['hot'],
    'bad journal mode rejected at open' => static function () use ($databasePath, $pageSize): mixed {
        try {
            SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'wal');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'bad page size rejected at open' => static function () use ($databasePath): mixed {
        try {
            SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, 1000, 'delete');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'rollback commit sorts page numbers' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize)['database_pages'],
    'rollback commit delete operation order' => static fn (): mixed => $ops(SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'full', 'delete')),
    'rollback commit truncate operation order' => static fn (): mixed => $ops(SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'normal', 'truncate')),
    'rollback commit persist operation order' => static fn (): mixed => $ops(SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'normal', 'persist')),
    'rollback commit sync off skips syncs' => static fn (): mixed => $ops(SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'off', 'delete')),
    'rollback commit full journal sync reason' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'full', 'delete')['operations'][1]['reason'],
    'rollback commit extra journal sync reason' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'extra', 'delete')['operations'][1]['reason'],
    'rollback commit database bytes' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize)['database_bytes'],
    'rollback commit journal bytes' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize)['journal_bytes'],
    'rollback commit page write offsets' => static function () use ($databasePath, $journalBytes, $pages, $pageSize): mixed {
        $writes = array_values(array_filter(
            SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize)['operations'],
            static fn (array $operation): bool => ($operation['path'] ?? '') === $databasePath && ($operation['op'] ?? '') === 'write'
        ));
        return array_column($writes, 'offset');
    },
    'rollback commit persist header bytes' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, 'full', 'persist')['operations'][5]['bytes'],
    'rollback commit dependencies include durable ordering' => static fn (): mixed => in_array('durable-journal-before-database-write', SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize)['dependencies'], true),
    'rollback commit read only rejected' => static function () use ($databasePath, $journalBytes, $pages, $pageSize): mixed {
        try {
            SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $pages, $pageSize, readOnly: true);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'temporary commit preserves requested mode' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize, 'full', 'persist')['requested_journal_mode'],
    'temporary commit effective mode is delete' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize, 'full', 'persist')['journal_mode'],
    'temporary commit journal path is distinct' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize)['journal_path'],
    'temporary commit deletes temp journal' => static fn (): mixed => in_array('delete_temporary_rollback_journal_after_commit', $reasons(SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize, 'normal', 'truncate')), true),
    'temporary commit directory sync uses temp dir' => static fn (): mixed => SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize, 'normal', 'truncate')['operations'][6]['path'],
    'temporary commit sync off omits directory sync' => static fn (): mixed => $ops(SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, '/tmp/wp-temp-journal', $journalBytes, $pages, $pageSize, 'off', 'persist')),
    'temporary commit default journal replacement' => static function () use ($databasePath, $journalBytes, $pages, $pageSize): mixed {
        try {
            SQLiteRollbackJournalCommitPlan::commitTemporary($databasePath, $databasePath . '-journal', $journalBytes, $pages, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'locking mode initial main normal' => static fn (): mixed => (new SQLitePragmaLockingMode())->execute('PRAGMA locking_mode')['locking_mode'],
    'locking mode exclusive assignment changes' => static fn (): mixed => (new SQLitePragmaLockingMode())->execute('PRAGMA locking_mode=exclusive')['changed'],
    'locking mode temp remains exclusive' => static fn (): mixed => (new SQLitePragmaLockingMode())->execute('PRAGMA temp.locking_mode=normal')['locking_mode'],
    'locking mode attached schema isolated' => static function (): mixed {
        $pragma = new SQLitePragmaLockingMode();
        $pragma->execute('PRAGMA wp.locking_mode=exclusive');
        return [$pragma->execute('PRAGMA wp.locking_mode')['locking_mode'], $pragma->execute('PRAGMA main.locking_mode')['locking_mode']];
    },
    'locking mode invalid assignment noops' => static fn (): mixed => (new SQLitePragmaLockingMode())->execute('PRAGMA locking_mode=invalid')['changed'],
    'locking mode parenthesized normal rows' => static fn (): mixed => (new SQLitePragmaLockingMode())->execute('PRAGMA locking_mode(normal)')['rows'],
    'locking mode malformed pragma rejected' => static function (): mixed {
        try {
            (new SQLitePragmaLockingMode())->execute('PRAGMA journal_mode');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'sync plan rollback normal targets' => static fn (): mixed => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'normal'), 'target'),
    'sync plan rollback normal flags' => static fn (): mixed => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'normal'), 'flag_names'),
    'sync plan rollback full persist targets' => static fn (): mixed => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'full', true), 'target'),
    'sync plan rollback powersafe skips directory' => static fn (): mixed => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'full', false, true), 'target'),
    'sync plan off targets remain skipped' => static fn (): mixed => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'off'), 'status'),
    'sync plan dataonly database flag' => static fn (): mixed => SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'full')[1]['flag_names'],
    'sync plan directory path' => static fn (): mixed => SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'normal')[2]['path'],
    'sync plan rejects relative path' => static function (): mixed {
        try {
            SQLiteVfsSyncPlan::rollbackCommitSequence('wp.sqlite', 'normal');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'delete open close status' => 'planned',
    'delete open close operation order' => ['write', 'delete', 'sync_directory'],
    'delete open zero header bytes' => 28,
    'delete close journal sidecar path' => $databasePath . '-journal',
    'delete close directory sync path' => '/wp-content/database',
    'truncate open close operation order' => ['write', 'truncate', 'sync_directory'],
    'truncate close zero bytes' => 0,
    'persist open close operation order' => ['write', 'write', 'sync_directory'],
    'persist close zero header payload' => 28,
    'persist close reason' => 'preserve_unused_journal_with_zeroed_header',
    'uppercase journal mode normalizes' => 'persist',
    'read only transaction open is blocked' => 'read_only_database_handle',
    'immutable transaction open is blocked' => 'immutable_database_handle',
    'null existing journal reports no hot journal' => null,
    'empty existing journal is non hot' => false,
    'bad journal mode rejected at open' => 'rejected',
    'bad page size rejected at open' => 'rejected',
    'rollback commit sorts page numbers' => [1, 3],
    'rollback commit delete operation order' => ['write', 'sync', 'write', 'write', 'sync', 'delete', 'sync_directory'],
    'rollback commit truncate operation order' => ['write', 'sync', 'write', 'write', 'sync', 'truncate', 'sync_directory'],
    'rollback commit persist operation order' => ['write', 'sync', 'write', 'write', 'sync', 'write', 'sync_directory'],
    'rollback commit sync off skips syncs' => ['write', 'write', 'write', 'delete'],
    'rollback commit full journal sync reason' => 'sync_rollback_journal',
    'rollback commit extra journal sync reason' => 'sync_rollback_journal_fullfsync',
    'rollback commit database bytes' => 1024,
    'rollback commit journal bytes' => 96,
    'rollback commit page write offsets' => [0, 1024],
    'rollback commit persist header bytes' => 28,
    'rollback commit dependencies include durable ordering' => true,
    'rollback commit read only rejected' => 'rejected',
    'temporary commit preserves requested mode' => 'persist',
    'temporary commit effective mode is delete' => 'delete',
    'temporary commit journal path is distinct' => '/tmp/wp-temp-journal',
    'temporary commit deletes temp journal' => true,
    'temporary commit directory sync uses temp dir' => '/tmp',
    'temporary commit sync off omits directory sync' => ['write', 'write', 'write', 'delete'],
    'temporary commit default journal replacement' => 'rejected',
    'locking mode initial main normal' => 'normal',
    'locking mode exclusive assignment changes' => true,
    'locking mode temp remains exclusive' => 'exclusive',
    'locking mode attached schema isolated' => ['exclusive', 'normal'],
    'locking mode invalid assignment noops' => false,
    'locking mode parenthesized normal rows' => [['locking_mode' => 'normal']],
    'locking mode malformed pragma rejected' => 'rejected',
    'sync plan rollback normal targets' => ['rollback_journal', 'database', 'directory'],
    'sync plan rollback normal flags' => [['normal'], ['normal', 'dataonly'], ['normal']],
    'sync plan rollback full persist targets' => ['rollback_journal', 'database', 'rollback_journal_header', 'directory'],
    'sync plan rollback powersafe skips directory' => ['rollback_journal', 'database'],
    'sync plan off targets remain skipped' => ['skipped', 'skipped', 'planned'],
    'sync plan dataonly database flag' => ['full', 'dataonly'],
    'sync plan directory path' => '/wp-content/database',
    'sync plan rejects relative path' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['transaction journal mode corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
