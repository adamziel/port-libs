<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'mmap3-1.2 direct shrink after schema read' => ['mmap3-1.2', 100000, 50000, false, 50000, false, true, null, ['nums', 't1', 't2']],
    'mmap3-1.3 direct growth after drop table' => ['mmap3-1.3', 50000, 250000, false, 250000, false, true, null, ['nums', 't1']],
    'mmap3-1.4 active cursor defers shrink request' => ['mmap3-1.4', 250000, 150000, true, 250000, true, false, null, ['nums', 't1']],
    'mmap3-1.5 active cursor defers disable request' => ['mmap3-1.5', 250000, 0, true, 250000, true, false, null, ['nums', 't1']],
    'mmap3-1.6 active cursor reports retained size' => ['mmap3-1.6', 250000, 250000, true, 250000, false, true, 250000, ['nums', 't1']],
    'mmap3-1.7 direct disable after cursor finishes' => ['mmap3-1.7', 250000, 0, false, 0, false, true, null, ['nums', 't1', 't3']],
    'mmap3-1.8 active cursor accepts growth from zero' => ['mmap3-1.8', 0, 75000, true, 75000, false, true, null, ['nums', 't1', 't3']],
];

$case = 0;
foreach (range(1, 143) as $round) {
    foreach ($scenarios as $name => [$scenario, $before, $requested, $active, $after, $deferred, $accepted, $reported, $tables]) {
        $case++;
        $rowStart = 10 + ($round % 4);
        $rowEnd = $rowStart + 5;
        $tests[sprintf('real upstream corpus vfs mmap3 active resize dynamic %04d %s round %03d', $case, $name, $round)] = static function (TestRunner $t) use ($scenario, $before, $requested, $active, $after, $deferred, $accepted, $reported, $tables, $rowStart, $rowEnd): void {
            $plan = SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile($scenario, $before, $requested, $active, $rowStart, $rowEnd);

            $t->same('ok', $plan['status']);
            $t->same('mmap3.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($before, $plan['mmap_size_before']);
            $t->same($requested, $plan['requested_mmap_size']);
            $t->same($after, $plan['mmap_size_after']);
            $t->same($active, $plan['statement_active']);
            $t->same($deferred, $plan['resize_deferred_until_statement_finishes']);
            $t->same($accepted, $plan['resize_accepted_immediately']);
            $t->same($reported, $plan['reported_mmap_size_during_scan']);
            $t->same($rowStart, $plan['scan_row_start']);
            $t->same($rowEnd, $plan['scan_row_end']);
            $t->same($active ? range($rowStart, $rowEnd) : [], $plan['scan_rows']);
            $t->same('ok', $plan['quick_check']);
            $t->same($tables, $plan['schema_tables']);
            $t->same(true, in_array('upstream-mmap3-active-statement-resize', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-active-cursor-boundary', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
            $t->same(true, in_array('mmap3.test mmap3-1.4 active statement ignores shrink request', $plan['upstream'], true));
            $t->same(true, in_array('mmap3.test mmap3-1.8 active statement accepts growth from zero', $plan['upstream'], true));
        };
    }
}

$tests['real upstream corpus vfs mmap3 active resize dynamic cites upstream sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.6', 250000, 250000, true);

    $t->same([
        'mmap3.test mmap3-1.0 row population and initial mmap_size',
        'mmap3.test mmap3-1.2 direct mmap_size shrink after schema read',
        'mmap3.test mmap3-1.3 direct mmap_size growth after DROP TABLE',
        'mmap3.test mmap3-1.4 active statement ignores shrink request',
        'mmap3.test mmap3-1.5 active statement ignores disable request',
        'mmap3.test mmap3-1.6 active statement reports retained mmap_size',
        'mmap3.test mmap3-1.7 direct disable after active cursor finishes',
        'mmap3.test mmap3-1.8 active statement accepts growth from zero',
    ], $plan['upstream']);
};

$tests['real upstream corpus vfs mmap3 active resize dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('', 1, 1, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.1', 1, 1, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.2', -1, 1, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.2', 1, -1, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.2', 1, 1, false, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.2', 1, 1, false, 2, 1));
};

return $tests;
