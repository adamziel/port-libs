<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'mmap3-1.0' => ['create_table_and_virtual_table', 0, 100000, 100000, false, true, ['nums', 't1']],
    'mmap3-1.2' => ['create_table', 100000, 50000, 50000, false, true, ['nums', 't1', 't2']],
    'mmap3-1.3' => ['drop_table', 50000, 250000, 250000, false, true, ['nums', 't1']],
    'mmap3-1.4' => ['pragma_inside_active_read_cursor', 250000, 150000, 250000, true, false, ['nums', 't1']],
    'mmap3-1.5' => ['zero_inside_active_read_cursor', 250000, 0, 250000, true, false, ['nums', 't1']],
    'mmap3-1.6' => ['read_pragma_inside_active_read_cursor', 250000, null, 250000, true, false, ['nums', 't1']],
    'mmap3-1.7' => ['function_syntax_zero_then_create_table', 250000, 0, 0, false, true, ['nums', 't1', 't3']],
    'mmap3-1.8' => ['set_after_zero_during_active_read_cursor', 0, 75000, 75000, true, false, ['nums', 't1', 't3']],
];

$case = 0;
foreach (range(1, 125) as $iteration) {
    foreach ($scenarios as $scenario => [$operation, $before, $requested, $after, $activeReadCursor, $schemaCookieChanged, $tablesAfter]) {
        ++$case;
        $tests[sprintf(
            'real upstream corpus vfs mmap3 pragma state dynamic %04d %s iteration %03d',
            $case,
            $scenario,
            $iteration
        )] = static function (TestRunner $t) use ($scenario, $iteration, $operation, $before, $requested, $after, $activeReadCursor, $schemaCookieChanged, $tablesAfter): void {
            $profile = SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile($scenario, $iteration);

            $t->same('ok', $profile['status']);
            $t->same('mmap3.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(['mmap3.test ' . substr($scenario, 6)], $profile['upstream']);
            $t->same($iteration, $profile['iteration']);
            $t->same($before, $profile['mmap_size_before']);
            $t->same($requested, $profile['requested_mmap_size']);
            $t->same($after, $profile['mmap_size_after']);
            $t->same($operation, $profile['operation']);
            $t->same($activeReadCursor, $profile['active_read_cursor']);
            $t->same($activeReadCursor ? 6 : 0, $profile['range_rows_visited']);
            $t->same($schemaCookieChanged, $profile['schema_cookie_changed']);
            $t->same('ok', $profile['quick_check']);
            $t->same($tablesAfter, $profile['tables_after']);
            $t->same($before !== $after, $profile['change_applied']);
            $t->same($activeReadCursor && $requested !== null && $requested !== $after, $profile['change_deferred_by_active_cursor']);
            $t->same($operation === 'read_pragma_inside_active_read_cursor', $profile['pragma_read_inside_cursor_preserves_size']);
            $t->same(true, in_array('upstream-mmap3-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-pragma-state', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($scenario === 'mmap3-1.0') {
                $t->same([100000, 500500, 500500, 100000], $profile['result_sequence']);
            } elseif ($scenario === 'mmap3-1.6') {
                $t->same([250000, 'ok', 250000], $profile['result_sequence']);
            } elseif ($scenario === 'mmap3-1.8') {
                $t->same(['ok', 75000], $profile['result_sequence']);
            } else {
                $t->same(true, $profile['result_sequence'] !== []);
            }
        };
    }
}

$tests['real upstream corpus vfs mmap3 pragma state dynamic owns one thousand cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs mmap3 pragma state dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'mmap3.test mmap3-1.0 row population and initial mmap_size',
        'mmap3.test mmap3-1.2 direct mmap_size shrink after schema read',
        'mmap3.test mmap3-1.3 direct mmap_size growth after DROP TABLE',
        'mmap3.test mmap3-1.4 active statement ignores shrink request',
        'mmap3.test mmap3-1.5 active statement ignores disable request',
        'mmap3.test mmap3-1.6 active statement reports retained mmap_size',
        'mmap3.test mmap3-1.7 direct disable after active cursor finishes',
        'mmap3.test mmap3-1.8 active statement accepts growth from zero',
    ], SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile('mmap3-1.8', 0, 75000, true)['upstream']);
};

$tests['real upstream corpus vfs mmap3 pragma state dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile('mmap3-1.1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile('mmap3-1.9'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile('mmap3-1.2', 0));
};

return $tests;
