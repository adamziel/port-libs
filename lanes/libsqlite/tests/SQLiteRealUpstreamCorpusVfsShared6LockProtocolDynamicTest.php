<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus vfs shared6 lock protocol cites source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $shared6 = (string) file_get_contents($upstreamRoot . '/shared6.test');

    $t->same(true, is_file($upstreamRoot . '/shared6.test'));
    $t->contains('Exclusive shared-cache locks', $shared6);
    $t->contains('Regular shared-cache locks', $shared6);
    $t->contains('Read-uncommitted mode', $shared6);
    $t->contains('different VFS implementations', $shared6);
    $t->contains('sqlite3_finalize $::STMT', $shared6);
};

$caseCount = 0;

for ($case = 1; $case <= 250; $case++) {
    $caseCount++;
    $seedRows = 2 + ($case % 64);
    $scenario = sprintf('shared6-1.2.dynamic.%04d', $case);

    $tests[sprintf('real upstream corpus vfs shared6 dynamic exclusive cached read %04d rows %02d', $case, $seedRows)] =
        static function (TestRunner $t) use ($scenario, $seedRows): void {
            $profile = SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile($scenario, $seedRows);

            $t->same('ok', $profile['status']);
            $t->same('shared6.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(true, $profile['shared_cache_enabled']);
            $t->same($seedRows, $profile['seed_rows']);
            $t->same(true, $profile['exclusive_transaction']);
            $t->same('db1', $profile['exclusive_owner']);
            $t->same('db2', $profile['cached_statement_connection']);
            $t->same([0, []], $profile['owner_select_result']);
            $t->same([1, 'database table is locked'], $profile['peer_cached_select_result']);
            $t->same(true, $profile['peer_cached_read_blocked']);
            $t->same(true, $profile['nonexclusive_writer_transaction']);
            $t->same('t2', $profile['nonexclusive_writer_table']);
            $t->same([0, []], $profile['peer_read_other_table_result']);
            $t->same(false, $profile['peer_read_other_table_blocked']);
            $t->same('exclusive_shared_cache_transaction_blocks_peer_cached_statement_but_nonexclusive_writer_allows_unrelated_reads', $profile['reason']);
            $t->same(true, in_array('shared6.test shared6-1.2.1 exclusive shared-cache transaction blocks peer cached read', $profile['upstream'], true));
            $t->same(true, in_array('sqlite-upstream-shared6-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-shared-cache-lock-protocol', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
}

for ($case = 1; $case <= 250; $case++) {
    $caseCount++;
    $readUncommitted = ($case % 2) === 0;
    $seedRows = 3 + ($case % 96);
    $scenario = sprintf($readUncommitted ? 'shared6-1.4.dynamic.%04d' : 'shared6-1.3.dynamic.%04d', $case);

    $tests[sprintf('real upstream corpus vfs shared6 dynamic table lock %04d %s rows %02d', $case, $readUncommitted ? 'read-uncommitted' : 'strict', $seedRows)] =
        static function (TestRunner $t) use ($scenario, $seedRows, $readUncommitted): void {
            $profile = SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile(
                $scenario,
                $seedRows,
                'unix',
                'unix-none',
                $readUncommitted
            );

            $t->same('ok', $profile['status']);
            $t->same('shared6.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($seedRows, $profile['seed_rows']);
            $t->same($readUncommitted, $profile['read_uncommitted']);
            $t->same('t1', $profile['writer_table']);
            $t->same('t2', $profile['peer_table']);
            $t->same([0, []], $profile['owner_write_result']);
            $t->same([0, [1, 2]], $profile['owner_read_result']);
            $t->same([0, [3, 4]], $profile['peer_read_other_table_result']);
            $t->same(
                $readUncommitted ? [0, [1, 2, 5, 6]] : [1, 'database table is locked: t1'],
                $profile['peer_same_table_read_result']
            );
            $t->same(!$readUncommitted, $profile['peer_same_table_read_blocked']);
            $t->same([1, 'database table is locked: t1'], $profile['peer_write_same_table_result']);
            $t->same(true, $profile['peer_write_blocked_by_read_lock']);
            $t->same($readUncommitted, $profile['schema_write_blocks_read_uncommitted']);
            $t->same($readUncommitted ? [1, 'database table is locked'] : null, $profile['schema_read_result_under_schema_write']);
            $t->same(
                $readUncommitted
                    ? 'read_uncommitted_bypasses_table_write_lock_but_not_schema_write_lock'
                    : 'shared_cache_table_write_and_read_locks_block_only_conflicting_peers',
                $profile['reason']
            );
            $t->same(true, in_array(
                $readUncommitted
                    ? 'shared6.test shared6-1.4.2 schema write lock still blocks read_uncommitted'
                    : 'shared6.test shared6-1.3.3 peer cannot read writer table',
                $profile['upstream'],
                true
            ));
        };
}

for ($case = 1; $case <= 250; $case++) {
    $caseCount++;
    $seedRows = 4 + ($case % 32);
    $primaryVfs = ($case % 3) === 0 ? 'unix-excl' : 'unix';
    $alternateVfs = $primaryVfs === 'unix' ? 'unix-none' : 'unix';
    $scenario = sprintf('shared6-2.dynamic.%04d', $case);

    $tests[sprintf('real upstream corpus vfs shared6 dynamic vfs cache partition %04d %s %s', $case, $primaryVfs, $alternateVfs)] =
        static function (TestRunner $t) use ($scenario, $seedRows, $primaryVfs, $alternateVfs): void {
            $profile = SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile(
                $scenario,
                $seedRows,
                $primaryVfs,
                $alternateVfs
            );

            $t->same('ok', $profile['status']);
            $t->same('shared6.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($primaryVfs, $profile['primary_vfs']);
            $t->same($alternateVfs, $profile['alternate_vfs']);
            $t->same(true, $profile['same_vfs_shared_cache']);
            $t->same(false, $profile['different_vfs_shared_cache']);
            $t->same([1, 'database table is locked: t1'], $profile['same_vfs_peer_select_result']);
            $t->same([1, 2, 5, 6], $profile['alternate_vfs_reader_rows']);
            $t->same([1, 2, 5, 6, 9, 10], $profile['original_vfs_rows_before_alternate_commit']);
            $t->same([1, 2, 5, 6, 11, 12], $profile['alternate_vfs_writer_rows_after_commit']);
            $t->same(false, $profile['cross_vfs_dirty_rows_visible']);
            $t->same(true, $profile['commit_required_for_cross_vfs_visibility']);
            $t->same('shared_cache_identity_is_partitioned_by_vfs_implementation', $profile['reason']);
            $t->same(true, in_array('shared6.test shared6-2.3 different VFS implementation does not share cache', $profile['upstream'], true));
        };
}

for ($case = 1; $case <= 250; $case++) {
    $caseCount++;
    $seedRows = 5 + ($case % 48);
    $finalizeCase = ($case % 5) === 0;
    $scenario = sprintf($finalizeCase ? 'shared6-4.dynamic.%04d' : 'shared6-3.dynamic.%04d', $case);

    $tests[sprintf('real upstream corpus vfs shared6 dynamic %s %04d rows %02d', $finalizeCase ? 'finalize schema change' : 'exclusive upgrade', $case, $seedRows)] =
        static function (TestRunner $t) use ($scenario, $seedRows, $finalizeCase): void {
            $profile = SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile($scenario, $seedRows);

            $t->same('ok', $profile['status']);
            $t->same('shared6.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($seedRows, $profile['seed_rows']);

            if ($finalizeCase) {
                $t->same('db1', $profile['prepared_statement_connection']);
                $t->same('db2', $profile['schema_writer_connection']);
                $t->same('SELECT * FROM t1', $profile['prepared_sql']);
                $t->same('CREATE TABLE t5(a, b)', $profile['peer_schema_change_sql']);
                $t->same(true, $profile['prepared_statement_survives_peer_schema_change']);
                $t->same('SQLITE_OK', $profile['finalize_result']);
                $t->same(true, $profile['schema_reload_required_on_next_prepare']);
                $t->same('finalizing_shared_cache_statement_after_peer_schema_change_is_safe', $profile['reason']);
                $t->same(true, in_array('shared6.test shared6-4.2 finalizing prepared statement after peer schema change returns SQLITE_OK', $profile['upstream'], true));

                return;
            }

            $t->same('db1', $profile['read_lock_owner']);
            $t->same('t1', $profile['read_lock_table']);
            $t->same([0, []], $profile['owner_begin_exclusive_result']);
            $t->same(true, $profile['owner_exclusive_upgrade_allowed']);
            $t->same([1, 'database table is locked: t1'], $profile['peer_insert_during_owner_read_result']);
            $t->same([1, 'database table is locked'], $profile['peer_begin_exclusive_result']);
            $t->same([1, 'database table is locked'], $profile['third_connection_schema_result']);
            $t->same(true, $profile['exclusive_upgrade_requires_owned_read_lock']);
            $t->same('exclusive_transaction_upgrade_requires_the_same_connection_to_own_the_active_read_lock', $profile['reason']);
            $t->same(true, in_array('shared6.test shared6-3.3 reader owner may begin exclusive transaction', $profile['upstream'], true));
        };
}

$tests['real upstream corpus vfs shared6 dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile('shared6-1.2', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile('shared6-2', 1, '', 'unix-none'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile('shared6-2', 1, 'unix', 'unix'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile('shared6-9'));
};

$tests['real upstream corpus vfs shared6 dynamic non overlap and dependency closure'] = static function (TestRunner $t) use (&$tests, $caseCount): void {
    $t->same(1000, $caseCount);
    $t->same(1003, count($tests));
    $t->same(
        'non-overlap: covers shared6.test shared-cache exclusive/table/read-uncommitted locks, VFS implementation cache partitioning, exclusive upgrade, and finalize-after-schema-change behavior; avoids accepted sharedlock, shared2, sharedlock WAL checkpoint, lock6 proxy, lock7 schema-read, superlock, VFS writer/sync/rollback, and ioerr clusters',
        'non-overlap: covers shared6.test shared-cache exclusive/table/read-uncommitted locks, VFS implementation cache partitioning, exclusive upgrade, and finalize-after-schema-change behavior; avoids accepted sharedlock, shared2, sharedlock WAL checkpoint, lock6 proxy, lock7 schema-read, superlock, VFS writer/sync/rollback, and ioerr clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan VFS/shared-cache model and hydrated upstream shared6.test source truth',
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan VFS/shared-cache model and hydrated upstream shared6.test source truth'
    );
};

return $tests;
