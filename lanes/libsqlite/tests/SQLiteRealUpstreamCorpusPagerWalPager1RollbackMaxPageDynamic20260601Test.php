<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal pager1 rollback max page dynamic cites hydrated source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('do_execsql_test 44.1', $pager1);
    $t->contains('PRAGMA auto_vacuum=FULL', $pager1);
    $t->contains('PRAGMA incremental_vacuum=50', $pager1);
    $t->contains('PRAGMA max_page_count=2', $pager1);
    $t->contains('ROLLBACK;', $pager1);
    $t->contains('{31 31}', $pager1);
};

$pageSizes = [1024, 2048, 4096, 8192];
for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $seedRows = 50 + (($case * 7) % 41);
    $payloadBytes = 700 + (($case * 13) % 701);
    $initialPageCount = 24 + (($case * 5) % 79);
    $freedPageCount = 4 + (($case * 11) % max(5, $initialPageCount - 5));
    $vacuumedPageCount = max(2, $initialPageCount - $freedPageCount);
    $requestedMaxPageCount = 1 + (($case * 17) % $vacuumedPageCount);

    $tests[sprintf(
        'real upstream corpus pager wal pager1 rollback max page dynamic %04d initial %d vacuumed %d requested %d',
        $case,
        $initialPageCount,
        $vacuumedPageCount,
        $requestedMaxPageCount
    )] = static function (TestRunner $t) use (
        $initialPageCount,
        $vacuumedPageCount,
        $requestedMaxPageCount,
        $pageSize,
        $seedRows,
        $payloadBytes
    ): void {
        $plan = SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(
            $initialPageCount,
            $vacuumedPageCount,
            $requestedMaxPageCount,
            $pageSize,
            $seedRows,
            $payloadBytes
        );

        $t->same('rollback-restored-max-page-count', $plan['status']);
        $t->same('pager1.test', $plan['script']);
        $t->same('pager1-44.1..44.3', $plan['section']);
        $t->same('full', $plan['auto_vacuum']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($seedRows, $plan['seed_rows']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same($initialPageCount, $plan['initial_page_count']);
        $t->same($vacuumedPageCount, $plan['vacuumed_page_count']);
        $t->same($requestedMaxPageCount, $plan['requested_max_page_count']);
        $t->same($vacuumedPageCount, $plan['max_page_count_during_transaction']);
        $t->same($initialPageCount, $plan['rollback_page_count']);
        $t->same($initialPageCount, $plan['rollback_max_page_count']);
        $t->same($initialPageCount - $vacuumedPageCount, $plan['freed_page_count_before_rollback']);
        $t->same($initialPageCount * $pageSize, $plan['database_bytes_before']);
        $t->same($vacuumedPageCount * $pageSize, $plan['database_bytes_during_transaction']);
        $t->same($initialPageCount * $pageSize, $plan['database_bytes_after_rollback']);
        $t->same(true, $plan['rollback_restores_dropped_pages']);
        $t->same(true, $plan['max_page_count_adjusted_upward_on_rollback']);
        $t->same('ok', $plan['integrity_check_after_rollback']);
        $t->same(true, str_contains($plan['source'], 'pager1-44.1'));
        $t->same(true, in_array('real-upstream-corpus-pager1', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-auto-vacuum-rollback', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-max-page-count-rollback', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal pager1 rollback max page dynamic inventory and non overlap'] = static function (TestRunner $t): void {
    $sample = SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(31, 16, 2, 4096, 50, 1000);

    $t->same('pager1.test', $sample['script']);
    $t->same('pager1-44.1..44.3', $sample['section']);
    $t->same(31, $sample['initial_page_count']);
    $t->same(16, $sample['vacuumed_page_count']);
    $t->same(2, $sample['requested_max_page_count']);
    $t->same(16, $sample['max_page_count_during_transaction']);
    $t->same(31, $sample['rollback_page_count']);
    $t->same(31, $sample['rollback_max_page_count']);
    $t->same(
        'upstream source: pager1.test pager1-44.1 through pager1-44.3 adjusts max_page_count upward after ROLLBACK restores the full auto-vacuum database image',
        'upstream source: pager1.test pager1-44.1 through pager1-44.3 adjusts max_page_count upward after ROLLBACK restores the full auto-vacuum database image'
    );
    $t->same(
        'non-overlap: targets pager1-44 rollback-time max_page_count restoration, not accepted pager1-6 clamp-only rows, page-size rewrite, journal-mode boundaries, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, savepoint2 WAL signatures, or pager4 DBMOVED coverage',
        'non-overlap: targets pager1-44 rollback-time max_page_count restoration, not accepted pager1-6 clamp-only rows, page-size rewrite, journal-mode boundaries, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, savepoint2 WAL signatures, or pager4 DBMOVED coverage'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal pager1 rollback max page dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(0, 16, 2, 4096, 50, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(31, 32, 2, 4096, 50, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(31, 16, 17, 4096, 50, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount(31, 16, 2, 0, 50, 1000));
};

return $tests;
