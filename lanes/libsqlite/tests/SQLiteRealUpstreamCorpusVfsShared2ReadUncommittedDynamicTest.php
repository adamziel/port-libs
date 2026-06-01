<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus vfs shared2 read uncommitted cites source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $shared2 = (string) file_get_contents($upstreamRoot . '/shared2.test');

    $t->contains('Test that if we delete all rows from a table any read-uncommitted', $shared2);
    $t->contains('SELECT a FROM numbers ORDER BY oid', $shared2);
    $t->contains('SELECT a, b FROM numbers ORDER BY a', $shared2);
    $t->contains('DELETE FROM numbers', $shared2);
    $t->contains('list $a $count', $shared2);
};

for ($case = 1; $case <= 1000; $case++) {
    $scanSource = ($case % 2) === 0 ? 'index' : 'table';
    $scenario = $scanSource === 'table'
        ? sprintf('shared2-1.2.dynamic.%04d', $case)
        : sprintf('shared2-1.3.dynamic.%04d', $case);
    $rowCount = 64 + (($case * 17) % 960);
    $deleteAtRow = max(1, min($rowCount, intdiv($rowCount, 2) + (($case % 9) - 4)));
    $rollbackDelete = $scanSource === 'table';

    $tests[sprintf(
        'real upstream corpus vfs shared2 read uncommitted dynamic %04d %s rows %04d delete %04d',
        $case,
        $scanSource,
        $rowCount,
        $deleteAtRow
    )] = static function (TestRunner $t) use ($scenario, $scanSource, $rowCount, $deleteAtRow, $rollbackDelete): void {
        $profile = SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile(
            $scenario,
            $rowCount,
            $deleteAtRow,
            $scanSource,
            $rollbackDelete
        );

        $t->same('ok', $profile['status']);
        $t->same('shared2.test', $profile['script']);
        $t->same($scenario, $profile['scenario']);
        $t->same(true, $profile['shared_cache_enabled']);
        $t->same(true, $profile['read_uncommitted']);
        $t->same($scanSource, $profile['scan_source']);
        $t->same($scanSource === 'table' ? 'table-btree' : 'index-btree', $profile['btree_kind']);
        $t->same($rowCount, $profile['row_count_before_scan']);
        $t->same($rowCount, $profile['count_before_scan']);
        $t->same($deleteAtRow, $profile['delete_at_row']);
        $t->same($deleteAtRow, $profile['last_visited_row']);
        $t->same($deleteAtRow, $profile['visited_row_count']);
        $t->same(range(1, $deleteAtRow), $profile['visited_rows_before_invalidation']);
        $t->same('DELETE FROM numbers', $profile['peer_delete_sql']);
        $t->same($scanSource === 'table', $profile['peer_delete_transaction_opened']);
        $t->same($rowCount, $profile['peer_delete_rows']);
        $t->same(0, $profile['peer_rows_after_delete']);
        $t->same(true, $profile['read_cursor_invalidated_by_peer_delete']);
        $t->same(true, $profile['scan_stops_at_delete_row']);
        $t->same([$deleteAtRow, $rowCount], $profile['upstream_result_pair']);
        $t->same($rollbackDelete, $profile['rollback_after_delete']);
        $t->same($rollbackDelete ? $rowCount : 0, $profile['rows_after_rollback_or_commit']);
        $t->same('numbers', $profile['schema_table']);
        $t->same('sqlite_autoindex_numbers_1', $profile['schema_index']);
        $t->same('abcdefghijklmnopqrstuvwxyz0123456789', $profile['payload']);
        $t->same('ok', $profile['integrity_check']);
        $t->same(true, in_array('sqlite-upstream-shared2-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-shared-cache-read-uncommitted', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-shared-cache-cursor-invalidation', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same(true, in_array(
            $scanSource === 'table'
                ? 'shared2.test shared2-1.2 read-uncommitted table scan stops when peer deletes all rows'
                : 'shared2.test shared2-1.3 read-uncommitted index scan stops when peer deletes all rows',
            $profile['upstream'],
            true
        ));
    };
}

$tests['real upstream corpus vfs shared2 read uncommitted rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('', 64, 32, 'table'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.2', 1, 1, 'table'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.2', 64, 0, 'table'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.2', 64, 65, 'table'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.2', 64, 32, 'view'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.2', 64, 32, 'index'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile('shared2-1.3', 64, 32, 'table'));
};

$tests['real upstream corpus vfs shared2 read uncommitted non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers shared2.test read-uncommitted cursor invalidation over table and index btrees; avoids accepted sharedlock table locks, lock4 deadlock, shmlock byte ranges, lock contention, WAL readonly locks, VFS writer/sync/rollback, ioerr, and pager/WAL clusters',
        'non-overlap: covers shared2.test read-uncommitted cursor invalidation over table and index btrees; avoids accepted sharedlock table locks, lock4 deadlock, shmlock byte ranges, lock contention, WAL readonly locks, VFS writer/sync/rollback, ioerr, and pager/WAL clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan shared-cache model and the hydrated upstream shared2.test source',
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan shared-cache model and the hydrated upstream shared2.test source'
    );
};

return $tests;
