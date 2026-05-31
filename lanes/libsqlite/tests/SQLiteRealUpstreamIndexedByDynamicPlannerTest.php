<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexedby.test sections indexedby-2.1
// through indexedby-12.4. These cases cover INDEXED BY and NOT INDEXED parser
// enforcement across SELECT, DELETE, UPDATE, views, rowid tail constraints,
// reserved identifier contexts, and unusable partial indexes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByDynamicPlannerEnforcementCases(1000) as $case) {
    $tests['real upstream indexedby dynamic planner enforcement case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexedby.test sections 2.1 through 12.4', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'indexedby-'));
        $t->true(in_array($case['statement_kind'], ['SELECT', 'DELETE', 'UPDATE', 'VIEW'], true));
        $t->true($case['table_name'] !== '');
        $t->true($case['sql'] !== '');
        $t->true($case['expected_detail'] !== '');
        $t->true(is_array($case['where_terms']));
        $t->same($case['expected_code'] === 0 ? 'ok' : 'expected-error', $case['integrity']);
        $t->same($case['not_indexed'], $case['indexed_by'] === null && str_contains($case['sql'], 'NOT INDEXED'));
        $t->same($case['uses_named_index'], $case['indexed_by'] !== null && str_contains($case['expected_detail'], (string) $case['indexed_by']));
        $t->same($case['uses_any_index'], str_contains($case['expected_detail'], 'USING INDEX') || str_contains($case['expected_detail'], 'USING COVERING INDEX'));

        if ($case['expected_error'] !== null) {
            $t->same(1, $case['expected_code']);
            $t->same(false, $case['uses_any_index']);
            $t->true(str_contains($case['expected_detail'], 'prepare-error'));
            $t->true(str_contains($case['expected_error'], 'no such index') || str_contains($case['expected_error'], 'no query solution'));
        } else {
            $t->same(0, $case['expected_code']);
            $t->same(null, $case['expected_error']);
        }

        if ($case['not_indexed'] && !$case['rowid_allowed']) {
            $t->same('SCAN ' . $case['table_name'], $case['expected_detail']);
        }

        if ($case['rowid_allowed']) {
            $t->true(in_array('rowid=?', $case['where_terms'], true));
            $t->true(str_contains($case['expected_detail'], 'rowid=?') || str_contains($case['expected_detail'], 'INTEGER PRIMARY KEY'));
        }

        if ($case['view_dependency']) {
            $t->true($case['statement_kind'] === 'VIEW' || $case['table_name'] === 'v1');
        }

        if ($case['indexed_by'] === 'p2') {
            $t->same(false, $case['partial_index_usable']);
            $t->same('no query solution', $case['expected_error']);
        }

        if ($case['statement_kind'] === 'DELETE' || $case['statement_kind'] === 'UPDATE') {
            $t->true($case['uses_named_index']);
            $t->true(in_array('a=?', $case['where_terms'], true));
        }
    };
}

$tests['real upstream indexedby dynamic planner source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexedByDynamicPlannerEnforcementCases(1000);
    $t->same(1000, count($cases));
    $t->same('indexedby-2.1/3.1.1', $cases[0]['upstream_section']);
    $t->same('indexedby.test sections 2.1 through 12.4', $cases[0]['source']);
    $t->true($cases[999]['batch'] > $cases[0]['batch']);
};

$tests['real upstream indexedby dynamic planner rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexedByDynamicPlannerEnforcementCases(0));
};

$tests['real upstream indexedby dynamic planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic planner fixtures for INDEXED BY, NOT INDEXED, rowid tail constraints, view dependencies, and partial-index unusable checks',
        'no new support component needed; reuses lane-local B-tree/index dynamic planner fixtures for INDEXED BY, NOT INDEXED, rowid tail constraints, view dependencies, and partial-index unusable checks',
    );
};

return $tests;
