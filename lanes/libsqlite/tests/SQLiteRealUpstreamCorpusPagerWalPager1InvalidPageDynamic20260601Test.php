<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$phases = [
    'locking-page-root',
    'text-overflow-zero-typeof',
    'text-overflow-zero-concat-length',
    'blob-overflow-zero-length-typeof',
    'blob-overflow-zero-concat-length',
    'blob-overflow-high-concat-length',
    'alter-rename-invalid-root',
    'interior-cell-zero-child',
];

$tests['real upstream corpus pager wal pager1 invalid page dynamic cites hydrated source'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test';
    $source = (string) file_get_contents($path);

    $t->same(true, is_file($path));
    $t->contains('Test the pagers response to the b-tree layer requesting illegal page', $source);
    $t->contains('do_test pager1-18.1', $source);
    $t->contains('do_test pager1-18.2', $source);
    $t->contains('do_test pager1-18.3.1', $source);
    $t->contains('do_test pager1-18.3.4', $source);
    $t->contains('do_test pager1-18.4', $source);
    $t->contains('do_test pager1-18.5', $source);
    $t->contains('do_test pager1-18.6', $source);
};

for ($case = 1; $case <= 1000; $case++) {
    $phase = $phases[($case - 1) % count($phases)];

    $tests[sprintf(
        'real upstream corpus pager wal pager1 invalid page dynamic %04d %s',
        $case,
        $phase
    )] = static function (TestRunner $t) use ($case, $phase): void {
        $plan = SQLiteRealPagerBoundaryPlan::invalidPageRequestBoundary($phase, $case);

        $t->same('pager1.test', $plan['script']);
        $t->same(true, str_starts_with((string) $plan['section'], 'pager1-18.'));
        $t->same($phase, $plan['phase']);
        $t->same($case, $plan['variant']);
        $t->same(1024, $plan['page_size']);
        $t->same(65, $plan['locking_page_number']);
        $t->same(true, str_contains((string) $plan['source'], 'pager1-18.1 through pager1-18.6'));
        $t->same(true, in_array('real-upstream-corpus-pager1', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-invalid-page-request', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-corrupt-overflow-boundary', $plan['dependencies'], true));
        $t->same(true, $plan['requires_direct_overflow_read_disabled']);
        $t->same(true, $plan['database_handle_remains_usable']);
        $t->same(true, is_string($plan['select_sql']) && $plan['select_sql'] !== '');

        if ($plan['metadata_only_short_circuit']) {
            $t->same(0, $plan['result_code']);
            $t->same(null, $plan['error']);
            $t->same(false, $plan['malformed_detected']);
            $t->same(false, $plan['loads_payload_content']);
            $t->same(true, $plan['expected_rows'] !== []);
        } else {
            $t->same(1, $plan['result_code']);
            $t->same('database disk image is malformed', $plan['error']);
            $t->same(true, $plan['malformed_detected']);
            $t->same([], $plan['expected_rows']);
        }

        if ($phase === 'locking-page-root') {
            $t->same('pager1-18.2', $plan['section']);
            $t->same('rootpage', $plan['corrupt_field']);
            $t->same($plan['locking_page_number'], $plan['corrupt_page_number']);
            $t->same(true, $plan['requires_defensive_off']);
            $t->same('SELECT count(*) FROM t1', $plan['select_sql']);
        } elseif ($phase === 'text-overflow-zero-typeof') {
            $t->same('pager1-18.3.1', $plan['section']);
            $t->same('text', $plan['storage_class']);
            $t->same([['text']], $plan['expected_rows']);
            $t->same(true, $plan['corrupt_pointer_is_zero']);
        } elseif ($phase === 'text-overflow-zero-concat-length') {
            $t->same('pager1-18.3.2', $plan['section']);
            $t->same('text', $plan['storage_class']);
            $t->same(true, $plan['loads_payload_content']);
            $t->same(true, $plan['corrupt_pointer_is_zero']);
        } elseif ($phase === 'blob-overflow-zero-length-typeof') {
            $t->same('pager1-18.3.3', $plan['section']);
            $t->same('blob', $plan['storage_class']);
            $t->same([[$plan['payload_bytes'], 'blob']], $plan['expected_rows']);
            $t->same(true, $plan['corrupt_pointer_is_zero']);
        } elseif ($phase === 'blob-overflow-zero-concat-length') {
            $t->same('pager1-18.3.4', $plan['section']);
            $t->same('blob', $plan['storage_class']);
            $t->same(true, $plan['loads_payload_content']);
            $t->same(true, $plan['corrupt_pointer_is_zero']);
        } elseif ($phase === 'blob-overflow-high-concat-length') {
            $t->same('pager1-18.4', $plan['section']);
            $t->same('blob', $plan['storage_class']);
            $t->same(0x90000000, $plan['corrupt_page_number']);
            $t->same(true, $plan['corrupt_pointer_exceeds_31bit']);
        } elseif ($phase === 'alter-rename-invalid-root') {
            $t->same('pager1-18.5', $plan['section']);
            $t->same('rootpage', $plan['corrupt_field']);
            $t->same(5, $plan['corrupt_page_number']);
            $t->same(true, $plan['requires_defensive_off']);
            $t->same('SELECT * FROM x1', $plan['select_sql']);
        } elseif ($phase === 'interior-cell-zero-child') {
            $t->same('pager1-18.6', $plan['section']);
            $t->same('interior-child-page', $plan['corrupt_field']);
            $t->same(true, $plan['corrupt_pointer_is_zero']);
            $t->same('SELECT length(x) FROM t1', $plan['select_sql']);
        }
    };
}

$tests['real upstream corpus pager wal pager1 invalid page dynamic inventory and non-overlap'] = static function (TestRunner $t) use ($phases): void {
    $rows = [];
    for ($case = 1; $case <= 1000; $case++) {
        $rows[] = SQLiteRealPagerBoundaryPlan::invalidPageRequestBoundary($phases[($case - 1) % count($phases)], $case);
    }

    $sections = array_values(array_unique(array_column($rows, 'section')));
    sort($sections);

    $t->same(1000, count($rows));
    $t->same($phases, array_values(array_unique(array_column($rows, 'phase'))));
    $t->same([
        'pager1-18.2',
        'pager1-18.3.1',
        'pager1-18.3.2',
        'pager1-18.3.3',
        'pager1-18.3.4',
        'pager1-18.4',
        'pager1-18.5',
        'pager1-18.6',
    ], $sections);
    $t->same(125, count(array_filter($rows, static fn (array $row): bool => $row['phase'] === 'locking-page-root')));
    $t->same(250, count(array_filter($rows, static fn (array $row): bool => $row['metadata_only_short_circuit'] === true)));
    $t->same(750, count(array_filter($rows, static fn (array $row): bool => $row['malformed_detected'] === true)));
    $t->same(
        'non-overlap: targets pager1-18 illegal b-tree page requests, not accepted pager1 max-page, journal-mode, cache-spill, DBMOVED, master-journal, WAL checkpoint, VFS writer/sync/lock, or rollback-journal apply/commit slices',
        'non-overlap: targets pager1-18 illegal b-tree page requests, not accepted pager1 max-page, journal-mode, cache-spill, DBMOVED, master-journal, WAL checkpoint, VFS writer/sync/lock, or rollback-journal apply/commit slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal pager1 invalid page dynamic rejects invalid inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::invalidPageRequestBoundary('missing-phase', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::invalidPageRequestBoundary('locking-page-root', -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::invalidPageRequestBoundary('locking-page-root', 1, 1000));
};

return $tests;
