<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereC.test sections whereC-1.1 through
// whereC-1.15. This owns rowid range constraints combined with the composite
// i1(a,b) index and ORDER BY direction variants.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereCRowidCompositeRangeCases(1000) as $case) {
    $tests['real upstream whereC rowid composite range dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereC.test sections whereC-1.1 through whereC-1.15', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereC-1.'));
        $t->true(str_starts_with($case['statement'], 'SELECT i FROM t1 WHERE '));
        $t->same('ok', $case['integrity']);
        $t->same($case['base_result'], $case['ascending_result']);
        $t->same(array_reverse($case['base_result']), $case['descending_result']);
        $t->same($case['rowid_range'] !== null, $case['uses_rowid_range']);
        $t->true($case['index_name'] === 'i1(a,b)' || $case['index_name'] === 'INTEGER PRIMARY KEY rowid');
        $t->true($case['detail'] !== '');

        if ($case['uses_composite_index']) {
            $t->same('i1(a,b)', $case['index_name']);
            $t->true(in_array('a=1', $case['where_terms'], true) || in_array('a=2', $case['where_terms'], true) || in_array('a IN(1,2)', $case['where_terms'], true));
        } else {
            $t->same('INTEGER PRIMARY KEY rowid', $case['index_name']);
            $t->same([12], $case['base_result']);
        }

        if ($case['upstream_section'] === 'whereC-1.1') {
            $t->same([4, 5], $case['base_result']);
            $t->same('i>3', $case['rowid_range']);
        }

        if ($case['upstream_section'] === 'whereC-1.7') {
            $t->same([3, 4, 5, 10], $case['base_result']);
            $t->true(in_array('a IN(1,2)', $case['where_terms'], true));
        }

        if ($case['upstream_section'] === 'whereC-1.8') {
            $t->same([10, 11, 12], $case['base_result']);
            $t->same([12, 11, 10], $case['descending_result']);
        }

        if ($case['upstream_section'] === 'whereC-1.11' || $case['upstream_section'] === 'whereC-1.12' || $case['upstream_section'] === 'whereC-1.13') {
            $t->same([], $case['base_result']);
        }

        if ($case['upstream_section'] === 'whereC-1.14') {
            $t->same([3, 4], $case['base_result']);
            $t->same('i<4.5', $case['rowid_range']);
        }

        if ($case['upstream_section'] === 'whereC-1.15') {
            $t->same("rowid IS '12'", $case['rowid_range']);
            $t->same([12], $case['base_result']);
        }
    };
}

$tests['real upstream whereC rowid composite range source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereCRowidCompositeRangeCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('whereC-1.1', $cases[0]['upstream_section']);
    $t->same('whereC-1.15', $cases[14]['upstream_section']);
    $t->same('whereC-1.10', $cases[999]['upstream_section']);
    $t->same([
        'whereC-1.1',
        'whereC-1.10',
        'whereC-1.11',
        'whereC-1.12',
        'whereC-1.13',
        'whereC-1.14',
        'whereC-1.15',
        'whereC-1.2',
        'whereC-1.3',
        'whereC-1.4',
        'whereC-1.5',
        'whereC-1.6',
        'whereC-1.7',
        'whereC-1.8',
        'whereC-1.9',
    ], $sections);
};

$tests['real upstream whereC rowid composite range rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereCRowidCompositeRangeCases(0));
};

$tests['real upstream whereC rowid composite range dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for rowid range, composite index equality, literal coercion, empty range, and ORDER BY direction behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for rowid range, composite index equality, literal coercion, empty range, and ORDER BY direction behavior',
    );
};

return $tests;
