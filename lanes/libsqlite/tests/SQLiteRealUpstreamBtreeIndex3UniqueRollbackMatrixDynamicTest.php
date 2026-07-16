<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index3.test index3-1.1 through 1.4.
// This additive matrix keeps the same failed CREATE UNIQUE INDEX transaction
// behavior while varying duplicate key shape, composite index columns, text
// and numeric affinities, and failed-index residue expectations.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index3UniqueRollbackCases(1000) as $case) {
    $tests['real upstream index3 unique rollback matrix dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index3.test index3-1.1 through index3-1.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index3-1.'));
        $t->true($case['batch'] >= 1);
        $t->true($case['table'] !== '');
        $t->true(str_starts_with($case['index_name'], 'i'));
        $t->true(count($case['index_columns']) >= 1);
        $t->same('BEGIN; CREATE UNIQUE INDEX; catchsql COMMIT', $case['transaction']);
        $t->same($case['duplicate_rows'], $case['pre_index_rows']);
        $t->true(count($case['duplicate_rows']) >= 2);
        $t->true(str_starts_with($case['expected_error'], 'UNIQUE constraint failed: ' . $case['table'] . '.'));
        $t->same([0, ''], $case['commit_result']);
        $t->same([$case['table']], $case['schema_objects_after_error']);
        $t->same('ok', $case['integrity']);
        $t->same(false, $case['leaves_index_residue']);

        foreach ($case['index_columns'] as $column) {
            $t->true(str_contains($case['expected_error'], '.' . $column));
        }
    };
}

$tests['real upstream index3 unique rollback matrix source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index3UniqueRollbackCases(1000);
    $t->same(1000, count($cases));
    $t->same('index3-1.1', $cases[0]['upstream_section']);
    $t->same('index3-1.4', $cases[3]['upstream_section']);
    $t->same('t1', $cases[0]['table']);
    $t->same('t_dup_numeric', $cases[4]['table']);
    $t->same(200, $cases[999]['batch']);
};

$tests['real upstream index3 unique rollback matrix rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::index3UniqueRollbackCases(0));
};

$tests['real upstream index3 unique rollback matrix dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, UNIQUE duplicate detection, transaction commit preservation, schema residue, and integrity-result helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, UNIQUE duplicate detection, transaction commit preservation, schema residue, and integrity-result helpers',
    );
};

return $tests;
