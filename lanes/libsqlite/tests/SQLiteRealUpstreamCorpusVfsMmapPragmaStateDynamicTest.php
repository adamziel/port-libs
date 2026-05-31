<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'mmap3-1.0' => [0, 100000, 100000, false, true, ['nums', 't1']],
    'mmap3-1.2' => [100000, 50000, 50000, false, true, ['nums', 't1', 't2']],
    'mmap3-1.3' => [50000, 250000, 250000, false, true, ['nums', 't1']],
    'mmap3-1.4' => [250000, 150000, 250000, true, false, ['nums', 't1']],
    'mmap3-1.5' => [250000, 0, 250000, true, false, ['nums', 't1']],
    'mmap3-1.6' => [250000, null, 250000, true, false, ['nums', 't1']],
    'mmap3-1.7' => [250000, 0, 0, false, true, ['nums', 't1', 't3']],
    'mmap3-1.8' => [0, 75000, 75000, true, false, ['nums', 't1', 't3']],
];

$caseNo = 0;
for ($iteration = 1; $iteration <= 126; $iteration++) {
    foreach ($scenarios as $scenario => [$before, $requested, $after, $activeReadCursor, $schemaCookieChanged, $tables]) {
        $caseNo++;
        $tests[sprintf('real upstream corpus vfs mmap pragma state dynamic %04d %s iteration %03d', $caseNo, $scenario, $iteration)] = static function (TestRunner $t) use ($scenario, $iteration, $before, $requested, $after, $activeReadCursor, $schemaCookieChanged, $tables): void {
            $profile = SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile($scenario, $iteration);

            $t->same('ok', $profile['status']);
            $t->same('mmap3.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(['mmap3.test ' . substr($scenario, 6)], $profile['upstream']);
            $t->same($iteration, $profile['iteration']);
            $t->same($before, $profile['mmap_size_before']);
            $t->same($requested, $profile['requested_mmap_size']);
            $t->same($after, $profile['mmap_size_after']);
            $t->same($activeReadCursor, $profile['active_read_cursor']);
            $t->same($activeReadCursor ? 6 : 0, $profile['range_rows_visited']);
            $t->same($schemaCookieChanged, $profile['schema_cookie_changed']);
            $t->same('ok', $profile['quick_check']);
            $t->same($tables, $profile['tables_after']);
            $t->same($before !== $after, $profile['change_applied']);
            $t->same($activeReadCursor && $requested !== null && $requested !== $after, $profile['change_deferred_by_active_cursor']);
            $t->same($scenario === 'mmap3-1.6', $profile['pragma_read_inside_cursor_preserves_size']);
            $t->same(true, in_array('upstream-mmap3-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-pragma-state', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs mmap pragma state dynamic cites exact upstream result sequences'] = static function (TestRunner $t): void {
    $expected = [
        'mmap3-1.0' => [100000, 500500, 500500, 100000],
        'mmap3-1.2' => [50000, 'nums', 't1', 't2', 'ok', 50000],
        'mmap3-1.3' => [250000, 'nums', 't1', 'ok', 250000],
        'mmap3-1.4' => ['ok', 250000],
        'mmap3-1.5' => ['ok', 250000],
        'mmap3-1.6' => [250000, 'ok', 250000],
        'mmap3-1.7' => [0, 'nums', 't1', 't3', 'ok', 0],
        'mmap3-1.8' => ['ok', 75000],
    ];

    foreach ($expected as $scenario => $result) {
        $profile = SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile($scenario);

        $t->same($result, $profile['result_sequence']);
        $t->same(true, in_array('mmap3.test ' . substr($scenario, 6), $profile['upstream'], true));
        $t->same('ok', $profile['quick_check']);
    }
};

$tests['real upstream corpus vfs mmap pragma state dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile(''));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile('mmap3-1.1'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile('mmap3-1.0', 0));
};

return $tests;
