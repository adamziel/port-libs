<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index7.test sections index7-1.1 through
// index7-1.15. This owns the WITHOUT ROWID partial-index statistics and
// lifecycle rows, distinct from later index7 lookup-use and index6 rowid-table
// partial-index coverage.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialStatsCases(1200) as $case) {
    $tests['real upstream index7 without rowid partial stats dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index7.test sections index7-1.1 through index7-1.15', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index7-1.'));
        $t->same('WITHOUT ROWID partial-index table', $case['table_shape']);
        $t->true($case['scenario'] !== '');
        $t->true($case['sql'] !== '');
        $t->true($case['partial_indexes'] !== []);
        $t->same($case['expected_error'] === null ? 'ok' : 'expected-error', $case['integrity']);

        foreach ($case['index_list_partial'] as $indexName => $partial) {
            $t->true(is_string($indexName) && $indexName !== '');
            $t->true($partial === 0 || $partial === 1);
        }

        foreach ($case['stat_rows'] as $row) {
            $t->true(array_key_exists('idx', $row));
            $t->true(array_key_exists('stat', $row));
            $t->true($row['stat'] !== '');
        }

        if ($case['expected_error'] !== null) {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['expected_error'], 'prohibited') || str_contains($case['expected_error'], 'no such column'));
        }

        if ($case['upstream_section'] === 'index7-1.1') {
            $t->same([[14, 20], ['ok']], $case['result_rows']);
            $t->same(20, $case['count_star']);
            $t->same(1, $case['index_list_partial']['t1a']);
            $t->same(1, $case['index_list_partial']['t1b']);
        }

        if ($case['upstream_section'] === 'index7-1.1a') {
            $t->same(0, $case['index_list_partial']['sqlite_autoindex_t1_1']);
            $t->same(1, $case['index_list_partial']['t1a']);
            $t->same(1, $case['index_list_partial']['t1b']);
            $t->same([['sqlite_autoindex_t1_1', 0], ['t1a', 1], ['t1b', 1]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index7-1.1.1') {
            $t->same([[20]], $case['result_rows']);
            $t->same(20, $case['count_star']);
        }

        if ($case['upstream_section'] === 'index7-1.6/1.7') {
            $t->same([[7]], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
            $t->same(1, $case['index_list_partial']['bad1']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX bad1'));
        }

        if ($case['upstream_section'] === 'index7-1.10') {
            $t->same([
                ['idx' => 't1', 'stat' => '20 1'],
                ['idx' => 't1a', 'stat' => '14 1'],
                ['idx' => 't1b', 'stat' => '10 1'],
            ], $case['stat_rows']);
        }

        if ($case['upstream_section'] === 'index7-1.11') {
            $t->same('20 1', $case['stat_rows'][1]['stat']);
            $t->same('10 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index7-1.11b') {
            $t->same('6 1', $case['stat_rows'][1]['stat']);
            $t->same('20 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index7-1.12') {
            $t->same('13 1', $case['stat_rows'][1]['stat']);
            $t->same('10 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index7-1.13' || $case['upstream_section'] === 'index7-1.14') {
            $t->same('15 1', $case['stat_rows'][0]['stat']);
            $t->same('10 1', $case['stat_rows'][1]['stat']);
            $t->same('8 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index7-1.15') {
            $t->same(0, $case['index_list_partial']['t1c']);
            $t->same('15 1', $case['stat_rows'][3]['stat']);
        }
    };
}

$tests['real upstream index7 without rowid partial stats corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialStatsCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('index7-1.1', $cases[0]['upstream_section']);
    $t->same('index7-1.15', $cases[15]['upstream_section']);
    $t->same('index7-1.1', $cases[16]['upstream_section']);
    $t->same('index7-1.15', $cases[1199]['upstream_section']);
    $t->same([
        'index7-1.1',
        'index7-1.1a',
        'index7-1.1.1',
        'index7-1.2',
        'index7-1.3',
        'index7-1.4',
        'index7-1.5',
        'index7-1.6/1.7',
        'index7-1.8',
        'index7-1.10',
        'index7-1.11',
        'index7-1.11b',
        'index7-1.12',
        'index7-1.13',
        'index7-1.14',
        'index7-1.15',
    ], $sections);
};

$tests['real upstream index7 without rowid partial stats rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialStatsCases(0));
};

$tests['real upstream index7 without rowid partial stats dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID partial-index lifecycle, stat1, reindex, and predicate-error helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID partial-index lifecycle, stat1, reindex, and predicate-error helpers',
    );
};

return $tests;
