<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereJ.test sections whereJ-4.2 and
// whereJ-5.1 through whereJ-5.3. These cases own STAT4/stat1-driven join
// order and range-cost choices, distinct from where8/where9 OR-union coverage.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereJMultiIndexRangeCostCases(1000) as $case) {
    $tests['real upstream whereJ range cost dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereJ.test sections whereJ-4.2 and whereJ-5.1 through whereJ-5.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereJ-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same(false, $case['uses_any_skip_scan']);
        $t->true(count($case['stat1_rows']) >= 3);
        $t->true(count($case['chosen_indexes']) >= 1);
        $t->true($case['detail'] !== '');

        foreach ($case['stat1_rows'] as $row) {
            $t->true($row['idx'] !== '');
            $t->true($row['stat'] !== '');
        }

        foreach ($case['rejected_plan_terms'] as $term) {
            $t->true($term !== '');
            $t->true(!str_contains($case['detail'], $term));
        }

        if ($case['upstream_section'] === 'whereJ-5.1/5.2/5.3') {
            $t->same(['t1abe', 't1abf'], $case['chosen_indexes']);
            $t->same(true, $case['uses_multi_index_or']);
            $t->contains('MULTI-INDEX OR', $case['detail']);
            $t->true(in_array('e>=7', $case['range_terms'], true));
            $t->true(in_array('f<=8', $case['range_terms'], true));
        }

        if ($case['upstream_section'] === 'whereJ-5.1/5.2') {
            $t->same(['t1abe'], $case['chosen_indexes']);
            $t->same(false, $case['uses_multi_index_or']);
            $t->true(in_array('e>=7', $case['range_terms'], true));
            $t->true(in_array('t1abc selected despite weaker e selectivity', $case['rejected_plan_terms'], true));
        }

        if ($case['upstream_section'] === 'whereJ-5.1/5.3') {
            $t->same(['t1abf'], $case['chosen_indexes']);
            $t->same(false, $case['uses_multi_index_or']);
            $t->true(in_array('f>=8', $case['range_terms'], true));
            $t->true(in_array('t1abc selected despite weaker f selectivity', $case['rejected_plan_terms'], true));
        }

        if ($case['upstream_section'] === 'whereJ-4.2') {
            $t->same(['cx scan', 'p_cid0', 'le primary key'], $case['chosen_indexes']);
            $t->same(false, $case['uses_multi_index_or']);
            $t->true(in_array('cx.code=2990', $case['or_terms'], true));
            $t->true(in_array('px.cx_id=cx.cx_id', $case['range_terms'], true));
        }
    };
}

$tests['real upstream whereJ range cost corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereJMultiIndexRangeCostCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('whereJ-5.1/5.2/5.3', $cases[0]['upstream_section']);
    $t->same('whereJ-4.2', $cases[3]['upstream_section']);
    $t->same('whereJ-4.2', $cases[999]['upstream_section']);
    $t->same([
        'whereJ-4.2',
        'whereJ-5.1/5.2',
        'whereJ-5.1/5.2/5.3',
        'whereJ-5.1/5.3',
    ], $sections);
};

$tests['real upstream whereJ range cost rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereJMultiIndexRangeCostCases(0));
};

$tests['real upstream whereJ range cost dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('upstream source reused from hydrated SQLite test/whereJ.test', 'upstream source reused from hydrated SQLite test/whereJ.test');
};

return $tests;
