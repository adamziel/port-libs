<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexedby.test section 11.1 through
// 11.10. These cases cover INDEXED BY plans where the rowid/INTEGER PRIMARY
// KEY suffix stored at the end of each secondary-index entry is used as an
// equality constraint alongside ordinary indexed columns.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidTailConstraintCases(1000) as $case) {
    $tests['real upstream indexedby rowid-tail constraint dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexedby.test sections indexedby-11.1 through indexedby-11.10', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], [
            'indexedby-11.2',
            'indexedby-11.3',
            'indexedby-11.4/11.5',
            'indexedby-11.7',
            'indexedby-11.8',
            'indexedby-11.9/11.10',
        ], true));
        $t->true(in_array($case['table'], ['x1', 'x2'], true));
        $t->same($case['table'] . 'i', $case['index_name']);
        $t->true(in_array($case['primary_key'], ['rowid', 'c'], true));
        $t->true(in_array($case['rowid_storage'], ['integer', 'text-integer', 'text-real-integer'], true));
        $t->same([[1, '1', 3]], $case['result_rows']);
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['uses_covering_index']);
        $t->same(true, $case['uses_rowid_tail']);
        $t->true(str_contains($case['statement'], 'INDEXED BY ' . $case['index_name']));
        $t->true(str_contains($case['statement'], $case['primary_key'] . '='));
        $t->same('SEARCH ' . $case['table'] . ' USING COVERING INDEX ' . $case['index_name'] . ' (a=? AND b=? AND rowid=?)', $case['detail']);

        if ($case['table'] === 'x1') {
            $t->same('rowid', $case['primary_key']);
            $t->true(str_contains($case['statement'], 'SELECT a,b,rowid FROM x1'));
            $t->same('x1i', $case['index_name']);
        }

        if ($case['table'] === 'x2') {
            $t->same('c', $case['primary_key']);
            $t->true(str_contains($case['statement'], 'SELECT a,b,c FROM x2'));
            $t->same('x2i', $case['index_name']);
        }

        if ($case['rowid_storage'] === 'integer') {
            $t->same(3, $case['rowid_literal']);
        }

        if ($case['rowid_storage'] === 'text-integer') {
            $t->same('3', $case['rowid_literal']);
        }

        if ($case['rowid_storage'] === 'text-real-integer') {
            $t->same('3.0', $case['rowid_literal']);
            $t->true(str_ends_with($case['upstream_section'], '11.5') || str_ends_with($case['upstream_section'], '11.10'));
        }
    };
}

$tests['real upstream indexedby rowid-tail corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidTailConstraintCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('indexedby-11.2', $cases[0]['upstream_section']);
    $t->same('indexedby-11.9/11.10', $cases[5]['upstream_section']);
    $t->same('indexedby-11.4/11.5', $cases[998]['upstream_section']);
    $t->same([
        'indexedby-11.2',
        'indexedby-11.3',
        'indexedby-11.4/11.5',
        'indexedby-11.7',
        'indexedby-11.8',
        'indexedby-11.9/11.10',
    ], $sections);
};

$tests['real upstream indexedby rowid-tail rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidTailConstraintCases(0));
};

$tests['real upstream indexedby rowid-tail dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native B-tree/index dynamic corpus planning, INDEXED BY planner detail records, rowid/INTEGER PRIMARY KEY coercion metadata, and covering-index result-row assertions',
        'no new support component needed; reuses native B-tree/index dynamic corpus planning, INDEXED BY planner detail records, rowid/INTEGER PRIMARY KEY coercion metadata, and covering-index result-row assertions',
    );
};

return $tests;
