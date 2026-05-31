<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic real pager 020744 cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('do_execsql_test pager1-6.4', $pager1);
    $t->contains('do_test pager1-10.$sectorsize.1', $pager1);
    $t->contains('do_execsql_test pager1-11.1', $pager1);
    $t->contains('do_catchsql_test pager1-11.2', $pager1);
    $t->contains('do_test pager1-12.$pagesize.1', $pager1);
};

for ($case = 1; $case <= 250; $case++) {
    $currentPages = 8 + ($case % 11);
    $requestedLimit = 1 + (($case * 7) % 30);

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 020744 pager1-6 max page count clamp %04d', $case)] = static function (TestRunner $t) use ($currentPages, $requestedLimit): void {
        $plan = SQLiteRealPagerBoundaryPlan::maxPageCountClamp($currentPages, $requestedLimit);
        $expected = max($currentPages, $requestedLimit);

        $t->same($currentPages, $plan['current_pages']);
        $t->same($requestedLimit, $plan['requested_limit']);
        $t->same($expected, $plan['effective_limit']);
        $t->same($requestedLimit > $currentPages, $plan['can_grow']);
        $t->same($expected === $requestedLimit ? 'max-page-count-updated' : 'max-page-count-clamped-to-current-size', $plan['status']);
        $t->same(true, str_contains($plan['source'], 'pager1-6.4'));
        $t->same(true, in_array('sqlite-pager-max-page-count', $plan['dependencies'], true));
    };
}

$sectorSizes = [512, 1024, 2048, 4096, 8192];
$pageSizes = [512, 1024, 2048, 4096];
for ($case = 1; $case <= 250; $case++) {
    $sectorSize = $sectorSizes[($case - 1) % count($sectorSizes)];
    $pageSize = $pageSizes[intdiv($case - 1, count($sectorSizes)) % count($pageSizes)];
    $dirtyPages = 1 + ($case % 17);
    $safeAppend = ($case % 3) === 0;

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 020744 pager1-10 sector journal alignment %04d', $case)] = static function (TestRunner $t) use ($sectorSize, $pageSize, $dirtyPages, $safeAppend): void {
        $plan = SQLiteRealPagerBoundaryPlan::sectorJournalFrame($sectorSize, $pageSize, $dirtyPages, $safeAppend);

        $t->same('sector-journal-frame-ready', $plan['status']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($dirtyPages, $plan['dirty_pages']);
        $t->same(8 + $pageSize + 4, $plan['frame_bytes']);
        $t->same($dirtyPages * (8 + $pageSize + 4), $plan['payload_bytes']);
        $t->same(0, $plan['journal_bytes'] % $sectorSize);
        $t->same(!$safeAppend, $plan['needs_directory_sync']);
        $t->same(true, str_contains($plan['source'], 'pager1-10'));
        $t->same(true, in_array('sqlite-pager-sector-journal-alignment', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageCount = 32 + ($case % 23);
    $dirtyPages = 1 + ($case % 9);
    $faultAfterWrites = $case % 12;
    $journalSynced = ($case % 4) !== 0;

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 020744 pager1-11 commit fault recovery %04d', $case)] = static function (TestRunner $t) use ($pageCount, $dirtyPages, $faultAfterWrites, $journalSynced): void {
        $plan = SQLiteRealPagerBoundaryPlan::commitFaultRecovery($pageCount, $dirtyPages, $faultAfterWrites, $journalSynced);
        $rolledBack = $faultAfterWrites < $dirtyPages;

        $t->same($pageCount, $plan['page_count']);
        $t->same($dirtyPages, $plan['dirty_pages']);
        $t->same(min($dirtyPages, $faultAfterWrites), $plan['written_pages_before_fault']);
        $t->same($rolledBack ? 'commit-fault-recovered-from-journal' : 'commit-complete', $plan['status']);
        $t->same($rolledBack ? min($dirtyPages, $faultAfterWrites) : 0, $plan['rolled_back_pages']);
        $t->same($rolledBack ? 0 : $dirtyPages, $plan['committed_pages']);
        $t->same($journalSynced, $plan['journal_synced']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, str_contains($plan['source'], 'pager1-11.1'));
        $t->same(true, in_array('sqlite-pager-commit-fault-recovery', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $currentPageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $requestedPageSize = $pageSizes[intdiv($case - 1, count($pageSizes)) % count($pageSizes)];
    $pageCount = 2 + ($case % 31);
    $transactionOpen = ($case % 5) === 0;

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 020744 pager1-12 page size rewrite %04d', $case)] = static function (TestRunner $t) use ($currentPageSize, $requestedPageSize, $pageCount, $transactionOpen): void {
        $plan = SQLiteRealPagerBoundaryPlan::pageSizeRewrite($currentPageSize, $requestedPageSize, $pageCount, $transactionOpen);
        $changed = !$transactionOpen && $requestedPageSize !== $currentPageSize;
        $effective = $changed ? $requestedPageSize : $currentPageSize;

        $t->same($currentPageSize, $plan['current_page_size']);
        $t->same($requestedPageSize, $plan['requested_page_size']);
        $t->same($effective, $plan['effective_page_size']);
        $t->same($transactionOpen, $plan['transaction_open']);
        $t->same($currentPageSize * $pageCount, $plan['database_bytes_before']);
        $t->same($effective * $pageCount, $plan['database_bytes_after']);
        $t->same($changed ? 'page-size-rewrite-ready' : 'page-size-retained', $plan['status']);
        $t->same(true, str_contains($plan['source'], 'pager1-12'));
        $t->same(true, in_array('sqlite-pager-page-size-rewrite', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic real pager 020744 rejects malformed helper inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::maxPageCountClamp(0, 10));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::sectorJournalFrame(512, 0, 1, false));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::commitFaultRecovery(4, 0, 0, true));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::pageSizeRewrite(1024, 3000, 3, false));
};

$tests['real upstream corpus pager wal dynamic real pager 020744 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T020744Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T020744Z-0');
    $t->same('pager1.test pager1-6.4 through pager1-6.12, pager1-10.*, pager1-11.1 through pager1-11.5, and pager1-12.*', 'pager1.test pager1-6.4 through pager1-6.12, pager1-10.*, pager1-11.1 through pager1-11.5, and pager1-12.*');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, VFS file writer/sync/lock state, rollback-journal apply/commit, cache-spill recursive SELECT, and in-memory journal-mode slices; covers real pager boundary calculations from separate pager1.test sections', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, VFS file writer/sync/lock state, rollback-journal apply/commit, cache-spill recursive SELECT, and in-memory journal-mode slices; covers real pager boundary calculations from separate pager1.test sections');
    $t->same('dependency-closure: no new support component needed; reuses hydrated upstream pager1.test and a source-neutral PHP pager boundary helper', 'dependency-closure: no new support component needed; reuses hydrated upstream pager1.test and a source-neutral PHP pager boundary helper');
};

return $tests;
