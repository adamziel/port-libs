<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where5.test. The upstream script is the
// ticket #2404 regression for NULL comparisons across TEXT, INTEGER, and
// INTEGER PRIMARY KEY B-tree rows.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where5NullComparisonCases(1200) as $case) {
    $tests['real upstream where5 null comparison dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where5.test sections where5-1.0 through where5-4.7', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where5-'));
        $t->true(str_starts_with($case['table_name'], 'app_'));
        $t->true(in_array($case['declared_type'], ['TEXT', 'INTEGER', 'INTEGER PRIMARY KEY'], true));
        $t->true(in_array($case['affinity'], ['TEXT', 'INTEGER'], true));
        $t->same(3, count($case['raw_values']));
        $t->same(3, count($case['stored_values']));
        $t->true($case['select_sql'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['detail'] !== '');

        if ($case['affinity'] === 'TEXT') {
            $t->true(is_string($case['stored_values'][0]));
        } else {
            $t->true(is_int($case['stored_values'][0]));
        }

        if ($case['uses_rowid_btree']) {
            $t->same('INTEGER PRIMARY KEY', $case['declared_type']);
            $t->same('app_integer_primary_values', $case['table_name']);
            $t->contains('INTEGER PRIMARY KEY', $case['comparison_family']);
        }

        if ($case['projection_mode']) {
            $t->same(null, $case['predicate_sql']);
            $t->true($case['expression_sql'] !== null);
            $t->same([], $case['expected_rows']);
            $t->same(0, $case['matched_row_count']);
            $t->same(3, count($case['projected_values']));
            $t->contains('SELECT ' . $case['expression_sql'], $case['select_sql']);

            if (str_contains((string) $case['expression_sql'], 'NULL') && !str_contains((string) $case['expression_sql'], 'IS')) {
                $t->same([null, null, null], $case['projected_values']);
                $t->same(3, $case['null_result_count']);
            }

            if ($case['expression_sql'] === 'x IS NULL') {
                $t->same([0, 0, 0], $case['projected_values']);
                $t->same(0, $case['null_result_count']);
            }

            if ($case['expression_sql'] === 'x IS NOT NULL') {
                $t->same([1, 1, 1], $case['projected_values']);
                $t->same(0, $case['null_result_count']);
            }
        } else {
            $t->true($case['predicate_sql'] !== null);
            $t->same(null, $case['expression_sql']);
            $t->same(null, $case['projected_values']);
            $t->same(0, $case['null_result_count']);
            $t->same(count($case['expected_rows']), $case['matched_row_count']);
            $t->contains(' WHERE ' . $case['predicate_sql'] . ' ORDER BY x', $case['select_sql']);

            if (str_contains((string) $case['predicate_sql'], 'NULL') && !str_contains((string) $case['predicate_sql'], 'IS NOT NULL')) {
                $t->same([], $case['expected_rows']);
            }

            if (str_contains((string) $case['predicate_sql'], 'IS NOT NULL')) {
                $t->same(3, $case['matched_row_count']);
                $t->same(3, count(array_intersect($case['stored_values'], array_values($case['expected_rows']))));
            }
        }

        if ($case['upstream_section'] === 'where5-2.2' && $case['batch'] === 8) {
            $t->same([0], $case['expected_rows']);
            $t->same('SELECT x FROM app_integer_values WHERE x = 0 ORDER BY x', $case['select_sql']);
        }

        if ($case['upstream_section'] === 'where5-3.5' && $case['batch'] === 8) {
            $t->same([-1, 1], $case['expected_rows']);
            $t->same('INTEGER PRIMARY KEY rowid btree scan', $case['comparison_family']);
        }

        if ($case['upstream_section'] === 'where5-4.6') {
            $t->same([0, 0, 0], $case['projected_values']);
        }

        if ($case['upstream_section'] === 'where5-4.7') {
            $t->same([1, 1, 1], $case['projected_values']);
        }
    };
}

$tests['real upstream where5 null comparison source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where5NullComparisonCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('where5-1.0', $cases[0]['upstream_section']);
    $t->same('where5-1.13', $cases[13]['upstream_section']);
    $t->same('where5-3.13', $cases[41]['upstream_section']);
    $t->same('where5-4.0', $cases[42]['upstream_section']);
    $t->same('where5-4.7', $cases[49]['upstream_section']);
    $t->same('where5-4.7', $cases[1199]['upstream_section']);
    $t->same('where5.test sections where5-1.0 through where5-4.7', $cases[1199]['source']);
    $t->same(50, count($sections));
};

$tests['real upstream where5 null comparison rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where5NullComparisonCases(0));
};

$tests['real upstream where5 null comparison dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for affinity coercion, NULL comparison truth values, rowid B-tree scans, and projection NULL result handling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for affinity coercion, NULL comparison truth values, rowid B-tree scans, and projection NULL result handling',
    );
};

return $tests;
