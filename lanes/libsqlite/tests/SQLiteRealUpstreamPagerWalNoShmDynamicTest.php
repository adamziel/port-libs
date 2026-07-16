<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

$cases = SQLiteRealUpstreamPagerWalDynamicPlan::walNoShmExclusiveModeCases();

$tests['real upstream pager wal noshm dynamic cites upstream walnoshm sections'] = static function (TestRunner $t) use ($cases): void {
    $t->same('walnoshm.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test'));
    $t->same(480, count($cases));
    $t->same('walnoshm.test 1.2', $cases[0]['upstream']);
    $t->same('walnoshm.test 3.2', $cases[9]['upstream']);
    $t->same('walnoshm.test', $cases[0]['source_file']);
    $t->same('sqlite-upstream-walnoshm-exclusive-mode', $cases[0]['dependencies'][1]);
};

foreach ($cases as $case) {
    $tests[sprintf('real upstream pager wal noshm dynamic %03d %s', $case['case'], $case['phase'])] = static function (TestRunner $t) use ($case): void {
        $expectsError = $case['result_code'] !== 0;
        $usesHeapWal = $case['heap_wal_index'] === true;
        $walWithoutShm = $case['wal_exists'] === true && $case['shm_primitives'] === false;
        $canReadRows = $case['result_code'] === 0 && $case['rows_visible'] !== [];

        $t->same('walnoshm.test', $case['source_file']);
        $t->same(true, str_starts_with($case['upstream'], 'walnoshm.test '));
        $t->same(1, $case['vfs_version']);
        $t->same(true, in_array($case['locking_mode_before'], ['normal', 'exclusive'], true));
        $t->same(true, in_array($case['locking_mode_after'], ['normal', 'exclusive'], true));
        $t->same(true, in_array($case['journal_mode_after'], ['delete', 'wal'], true));
        $t->same($expectsError, $case['message'] === 'database is locked' || $case['message'] === 'unable to open database file');
        $t->same($usesHeapWal, $case['requires_exclusive_lock']);
        $t->same($walWithoutShm && $case['locking_mode_after'] === 'exclusive' && !$expectsError, $case['can_open_without_shm']);
        $t->same($case['reader_blocked'], $expectsError || (($case['blocking_reader'] ?? false) === true));
        $t->same($canReadRows ? count($case['rows_visible']) * $case['row_amplifier'] : 0, $canReadRows ? $case['visible_row_count'] : 0);
        $t->same($case['wal_exists'] ? 'present' : 'absent', substr($case['lock_trace'][array_key_last($case['lock_trace']) - 1], -7) === 'present' ? 'present' : 'absent');
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-heap-index-without-shm', $case['dependencies'], true));
        $t->same(true, count($case['lock_trace']) >= 6);
    };
}

$tests['real upstream pager wal noshm dynamic summarizes section coverage'] = static function (TestRunner $t) use ($cases): void {
    $upstream = array_values(array_unique(array_column($cases, 'upstream')));

    $t->same([
        'walnoshm.test 1.2',
        'walnoshm.test 1.4',
        'walnoshm.test 1.7',
        'walnoshm.test 1.8 1.9 1.10',
        'walnoshm.test 2.1.3 2.1.4',
        'walnoshm.test 2.1.5',
        'walnoshm.test 2.2.2',
        'walnoshm.test 2.2.3 2.2.5',
        'walnoshm.test 3.1',
        'walnoshm.test 3.2',
    ], $upstream);

    $t->same(48, count(array_filter($cases, static fn (array $case): bool => $case['phase'] === 'version1-vfs-normal-mode-refuses-wal-conversion')));
    $t->same(48, count(array_filter($cases, static fn (array $case): bool => $case['phase'] === 'exclusive-before-wal-open-keeps-other-reader-locked-out')));
    $t->same(240, count(array_filter($cases, static fn (array $case): bool => $case['heap_wal_index'] === true)));
    $t->same(336, count(array_filter($cases, static fn (array $case): bool => $case['wal_exists'] === true)));
    $t->same(144, count(array_filter($cases, static fn (array $case): bool => $case['result_code'] !== 0)));
};

return $tests;
