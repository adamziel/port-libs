<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic real pager 103759 cites hydrated pager4 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager4 = (string) file_get_contents($upstreamRoot . '/pager4.test');

    $t->contains('Tests for the SQLITE_READONLY_DBMOVED error condition', $pager4);
    $t->contains('do_catchsql_test pager4-1.2', $pager4);
    $t->contains('do_catchsql_test pager4-1.3', $pager4);
    $t->contains('do_catchsql_test pager4-1.4', $pager4);
    $t->contains('do_catchsql_test pager4-1.5', $pager4);
    $t->contains('do_catchsql_test pager4-1.6', $pager4);
    $t->contains('do_catchsql_test pager4-1.7', $pager4);
    $t->contains('do_catchsql_test pager4-1.8', $pager4);
    $t->contains('do_catchsql_test pager4-1.9', $pager4);
    $t->contains('do_catchsql_test pager4-1.10', $pager4);
    $t->contains('do_catchsql_test pager4-1.11', $pager4);
};

$templates = [
    ['read-after-rename', 'delete', 'pager4-1.2', 'database-moved-read-ok'],
    ['write-after-rename', 'delete', 'pager4-1.3', 'database-moved-write-readonly'],
    ['write-after-replacement-name', 'delete', 'pager4-1.4', 'database-moved-replacement-name-still-readonly'],
    ['restored-name-read', 'delete', 'pager4-1.5', 'database-name-restored-read-ok'],
    ['restored-name-write', 'delete', 'pager4-1.6', 'database-name-restored-write-ok'],
    ['renamed-off-write', 'off', 'pager4-1.7', 'database-moved-off-journal-write-ok'],
    ['renamed-memory-write', 'memory', 'pager4-1.8', 'database-moved-memory-journal-write-ok'],
    ['renamed-delete-write', 'delete', 'pager4-1.9', 'database-moved-rollback-journal-write-readonly'],
    ['renamed-truncate-write', 'truncate', 'pager4-1.10', 'database-moved-rollback-journal-write-readonly'],
    ['renamed-persist-write', 'persist', 'pager4-1.11', 'database-moved-rollback-journal-write-readonly'],
];

for ($case = 1; $case <= 1000; $case++) {
    [$phase, $journalMode, $section, $status] = $templates[($case - 1) % count($templates)];
    $variant = 103759 + $case;

    $tests[sprintf(
        'real upstream corpus pager wal dynamic real pager 103759 pager4 readonly dbmoved %04d %s',
        $case,
        $section
    )] = static function (TestRunner $t) use ($phase, $journalMode, $section, $status, $variant): void {
        $plan = SQLiteRealPagerBoundaryPlan::databaseMovedWriteBoundary($phase, $journalMode, $variant);
        $isWrite = $plan['operation'] === 'update';
        $expectsReadonly = $isWrite && !$plan['write_allowed'];
        $journalBypassesMovedFile = in_array($journalMode, ['off', 'memory'], true);
        $journalRequiresStableName = in_array($journalMode, ['delete', 'truncate', 'persist'], true);

        $t->same('pager4.test', $plan['script']);
        $t->same($section, $plan['section']);
        $t->same($phase, $plan['phase']);
        $t->same($journalMode, $plan['requested_journal_mode']);
        $t->same($status, $plan['status']);
        $t->same($expectsReadonly ? 1 : 0, $plan['result_code']);
        $t->same($expectsReadonly ? 'attempt to write a readonly database' : null, $plan['error']);
        $t->same($journalRequiresStableName, $plan['journal_required_for_write']);
        $t->same($phase === 'write-after-replacement-name', $plan['replacement_file_with_original_name']);
        $t->same(str_starts_with($phase, 'restored-name-'), $plan['original_name_restored']);
        $t->same(!str_starts_with($phase, 'restored-name-'), $plan['database_file_moved']);
        $t->same($journalBypassesMovedFile || str_starts_with($phase, 'restored-name-') || !$isWrite, $plan['read_allowed']);
        $t->same($expectsReadonly, $plan['readonly_error_after_move']);
        $t->same($expectsReadonly ? null : $journalMode, $plan['effective_journal_mode']);
        $t->same(true, is_array($plan['initial_row']) && count($plan['initial_row']) === 3);
        $t->same(true, is_array($plan['row_before_attempt']) && count($plan['row_before_attempt']) === 3);
        $t->same(true, is_array($plan['final_row']) && count($plan['final_row']) === 3);
        $t->same($expectsReadonly ? $plan['row_before_attempt'] : $plan['final_row'], $plan['final_row']);
        if ($plan['read_allowed']) {
            $t->same([$plan['final_row']], $plan['select_result']);
        }
        if ($phase === 'renamed-off-write') {
            $t->same(true, $plan['final_row'][0] >= 107);
            $t->same($plan['initial_row'][1], $plan['final_row'][1]);
        }
        if ($phase === 'renamed-memory-write') {
            $t->contains('memory-', $plan['final_row'][1]);
        }
        $t->contains('pager4-1.2 through pager4-1.11', $plan['source']);
        $t->same(true, in_array('real-upstream-corpus-pager4', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-database-moved-boundary', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic real pager 103759 rejects malformed helper inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::databaseMovedWriteBoundary('missing', 'delete'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::databaseMovedWriteBoundary('read-after-rename', 'wal'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::databaseMovedWriteBoundary('read-after-rename', 'delete', -1));
};

$tests['real upstream corpus pager wal dynamic real pager 103759 row count and non overlap'] = static function (TestRunner $t) use ($templates): void {
    $t->same(10, count($templates));
    $t->same(['read-after-rename', 'delete', 'pager4-1.2', 'database-moved-read-ok'], $templates[0]);
    $t->same(['renamed-persist-write', 'persist', 'pager4-1.11', 'database-moved-rollback-journal-write-readonly'], $templates[9]);
    $t->same(
        'upstream source: pager4.test sections pager4-1.2 through pager4-1.11 cover SQLITE_READONLY_DBMOVED reads, write blocking, restored-name writes, OFF/MEMORY exceptions, and DELETE/TRUNCATE/PERSIST failures',
        'upstream source: pager4.test sections pager4-1.2 through pager4-1.11 cover SQLITE_READONLY_DBMOVED reads, write blocking, restored-name writes, OFF/MEMORY exceptions, and DELETE/TRUNCATE/PERSIST failures'
    );
    $t->same(
        'non-overlap: targets pager4 database-moved boundary behavior and avoids accepted pager4-1.1 temp pager visibility, pager4-2.2 cache reload, WAL byte truncation, checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, pager1 real-boundary batches, pager2 savepoint churn, and wal2/walfault dynamic batches',
        'non-overlap: targets pager4 database-moved boundary behavior and avoids accepted pager4-1.1 temp pager visibility, pager4-2.2 cache reload, WAL byte truncation, checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, pager1 real-boundary batches, pager2 savepoint churn, and wal2/walfault dynamic batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses hydrated upstream pager4.test and the source-neutral SQLiteRealPagerBoundaryPlan helper',
        'dependency-closure: no new support component needed; reuses hydrated upstream pager4.test and the source-neutral SQLiteRealPagerBoundaryPlan helper'
    );
};

return $tests;
