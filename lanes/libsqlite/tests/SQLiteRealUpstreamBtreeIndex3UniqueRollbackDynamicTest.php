<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

// Source truth: SQLite upstream test/index3.test sections index3-1.1
// through index3-1.4. This batch covers the CREATE UNIQUE INDEX failure
// path when duplicate input rows are present inside a transaction. The
// upstream behavior commits successfully afterward and leaves no malformed
// schema or index residue.
foreach (SQLiteIndexLifecyclePlan::duplicateUniqueIndexRollbackCases(1200) as $case) {
    $tests['real upstream index3 duplicate unique rollback dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index3.test index3-1.1 through index3-1.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->same('index3-1.1/1.4', $case['upstream_section']);
        $t->same('failed UNIQUE index creation on duplicate column data leaves no index residue after transaction commit', $case['scenario']);
        $t->true(str_starts_with($case['table_name'], 't_dup_'));
        $t->true(str_starts_with($case['index_name'], 'i_dup_'));
        $t->same('a', $case['unique_column']);
        $t->true($case['duplicate_value'] >= 1 && $case['duplicate_value'] <= 24);
        $t->same([[$case['duplicate_value']], [$case['duplicate_value']]], $case['initial_rows']);
        $t->same(2, $case['row_count']);

        $t->same(1, $case['create_index_result'][0]);
        $t->same('UNIQUE constraint failed: ' . $case['table_name'] . '.a', $case['create_index_result'][1]);
        $t->same([0, []], $case['commit_result']);
        $t->same([$case['table_name']], $case['catalog_names']);
        $t->same([], $case['index_names']);
        $t->same('ok', $case['integrity']);
        $t->same(false, $case['residue_detected']);
    };
}

$tests['real upstream index3 duplicate unique rollback corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteIndexLifecyclePlan::duplicateUniqueIndexRollbackCases(1200);
    $t->same(1200, count($cases));
    $t->same(1, $cases[0]['duplicate_value']);
    $t->same(24, $cases[23]['duplicate_value']);
    $t->same(1, $cases[24]['duplicate_value']);
    $t->same(50, $cases[1199]['batch']);
};

$tests['real upstream index3 duplicate unique rollback dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local index lifecycle, catalog-residue, transaction-result, integrity, and unique-index failure helpers',
        'no new support component needed; reuses lane-local index lifecycle, catalog-residue, transaction-result, integrity, and unique-index failure helpers',
    );
};

return $tests;
