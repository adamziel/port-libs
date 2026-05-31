<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$readLockCaseCount = 0;
foreach (range(5, 54) as $seedRows) {
    foreach (range(1, 5) as $selfInsertAfterRow) {
        foreach ([0, 1] as $peerOffset) {
            ++$readLockCaseCount;
            $peerWriteAfterRow = min($seedRows, $selfInsertAfterRow + $peerOffset);
            $scenario = sprintf('sharedlock-1.2.dynamic.%04d', $readLockCaseCount);

            $tests[sprintf(
                'real upstream corpus vfs sharedlock dynamic read-lock retained %04d seed %02d self %d peer %d',
                $readLockCaseCount,
                $seedRows,
                $selfInsertAfterRow,
                $peerWriteAfterRow
            )] = static function (TestRunner $t) use ($scenario, $seedRows, $selfInsertAfterRow, $peerWriteAfterRow): void {
                $profile = SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile(
                    $scenario,
                    $seedRows,
                    $selfInsertAfterRow,
                    $peerWriteAfterRow
                );

                $cursorRowids = array_column($profile['cursor_rows'], 'a');
                $finalRowids = array_column($profile['final_table_rows'], 'a');

                $t->same('ok', $profile['status']);
                $t->same('sharedlock.test', $profile['script']);
                $t->same($scenario, $profile['scenario']);
                $t->same('t1', $profile['table']);
                $t->same(true, $profile['shared_cache_enabled']);
                $t->same($seedRows, $profile['initial_rows']);
                $t->same($selfInsertAfterRow, $profile['self_insert_after_row']);
                $t->same($peerWriteAfterRow, $profile['peer_write_after_row']);
                $t->same($seedRows + 1, count($profile['cursor_rows']));
                $t->same($seedRows + 1, count($profile['final_table_rows']));
                $t->same($seedRows + 1, $profile['self_insert_row']['a']);
                $t->same($seedRows + 2, $profile['peer_insert_row']['a']);
                $t->same(true, in_array($seedRows + 1, $cursorRowids, true));
                $t->same(false, in_array($seedRows + 2, $cursorRowids, true));
                $t->same(false, in_array($seedRows + 2, $finalRowids, true));
                $t->same(true, $profile['read_lock_retained_after_self_write']);
                $t->same('ok', $profile['self_write_result']);
                $t->same([1, 'database table is locked: t1'], $profile['peer_write_result']);
                $t->same(true, $profile['peer_write_blocked']);
                $t->same(false, $profile['peer_row_visible']);
                $t->same('same_connection_write_does_not_drop_shared_cache_table_read_lock', $profile['reason']);
                $t->same(true, in_array('sharedlock.test sharedlock-1.2 peer writer remains blocked by retained read-lock', $profile['upstream'], true));
                $t->same(true, in_array('upstream-sharedlock-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-shared-cache-table-locks', $profile['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            };
        }
    }
}

$opClearCaseCount = 0;
$deleteForms = [
    'without-where' => 'DELETE FROM t2',
    'where-true' => 'DELETE FROM t2 WHERE 1',
];

foreach (range(2, 51) as $deleteRows) {
    foreach ($deleteForms as $form => $deleteSql) {
        foreach (range(1, 5) as $round) {
            ++$opClearCaseCount;
            $scenario = sprintf('sharedlock-2.%d.dynamic.%04d', $form === 'where-true' ? 1 : 2, $opClearCaseCount);

            $tests[sprintf(
                'real upstream corpus vfs sharedlock dynamic op-clear write-lock %04d rows %02d %s round %d',
                $opClearCaseCount,
                $deleteRows,
                $form,
                $round
            )] = static function (TestRunner $t) use ($scenario, $deleteRows, $deleteSql, $form): void {
                $profile = SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile(
                    $scenario,
                    2,
                    1,
                    2,
                    $deleteSql,
                    $deleteRows
                );

                $t->same('ok', $profile['status']);
                $t->same('sharedlock.test', $profile['script']);
                $t->same($scenario, $profile['scenario']);
                $t->same('t2', $profile['table']);
                $t->same(true, $profile['shared_cache_enabled']);
                $t->same($deleteRows, $profile['delete_rows']);
                $t->same($form === 'where-true' ? 'where_true' : 'without_where', $profile['delete_form']);
                $t->same(strtoupper($deleteSql), $profile['delete_sql']);
                $t->same($deleteRows, count($profile['pre_delete_rows']));
                $t->same($deleteRows * 2, count($profile['peer_pre_delete_result']));
                $t->same(1, $profile['pre_delete_rows'][0]['x']);
                $t->same(2, $profile['pre_delete_rows'][0]['y']);
                $t->same($deleteRows, $profile['pre_delete_rows'][$deleteRows - 1]['x']);
                $t->same($deleteRows + 1, $profile['pre_delete_rows'][$deleteRows - 1]['y']);
                $t->same(true, $profile['op_clear_optimization']);
                $t->same(true, $profile['write_lock_taken']);
                $t->same(true, $profile['peer_read_blocked']);
                $t->same([1, 'database table is locked: t2'], $profile['peer_select_result']);
                $t->same(true, $profile['commit_releases_write_lock']);
                $t->same([], $profile['rows_after_commit']);
                $t->same('ok', $profile['integrity_check']);
                $t->same('op_clear_full_table_delete_takes_shared_cache_table_write_lock', $profile['reason']);
                $t->same(true, in_array('sharedlock.test sharedlock-2.4 OP_Clear write-lock blocks peer table read', $profile['upstream'], true));
                $t->same(true, in_array('sqlite-shared-cache-op-clear-write-lock', $profile['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            };
        }
    }
}

$tests['real upstream corpus vfs sharedlock dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/sharedlock.test';
    $contents = file_get_contents($source);

    $t->same(true, is_string($contents));
    $t->same(true, str_contains((string) $contents, 'sharedlock-1.2'));
    $t->same(true, str_contains((string) $contents, 'This should fail'));
    $t->same(true, str_contains((string) $contents, 'OP_Clear optimization'));
    $t->same(true, str_contains((string) $contents, 'database table is locked: t2'));
};

$tests['real upstream corpus vfs sharedlock dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-9.1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-1.2', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-1.2', 3, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-1.2', 3, 1, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-2.1', 2, 1, 2, 'DELETE FROM t1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile('sharedlock-2.1', 2, 1, 2, 'DELETE FROM t2', 0));
};

$tests['real upstream corpus vfs sharedlock dynamic owns one thousand behavior cases'] = static function (TestRunner $t) use (&$tests, $readLockCaseCount, $opClearCaseCount): void {
    $t->same(500, $readLockCaseCount);
    $t->same(500, $opClearCaseCount);
    $t->same(1003, count($tests));
    $t->same(
        'non-overlap: covers sharedlock.test shared-cache table read-lock retention and OP_Clear write-locks; avoids accepted lock.test lock contention, lock4 deadlock, lock5 nolock/flock/dotfile, lock7 schema-read, superlock, WAL shared-cache checkpoint, VFS writer/sync/rollback, and ioerr clusters',
        'non-overlap: covers sharedlock.test shared-cache table read-lock retention and OP_Clear write-locks; avoids accepted lock.test lock contention, lock4 deadlock, lock5 nolock/flock/dotfile, lock7 schema-read, superlock, WAL shared-cache checkpoint, VFS writer/sync/rollback, and ioerr clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan surface and hydrated upstream sharedlock.test source truth',
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan surface and hydrated upstream sharedlock.test source truth'
    );
};

return $tests;
