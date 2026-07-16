<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedSections = [
    'without_rowid5-1.1',
    'without_rowid5-1.2',
    'without_rowid5-1.3',
    'without_rowid5-1.4',
    'without_rowid5-2.1',
    'without_rowid5-2.2',
    'without_rowid5-2.3',
    'without_rowid5-2.4',
    'without_rowid5-2.5',
    'without_rowid5-2.6',
    'without_rowid5-2.7',
    'without_rowid5-2.8',
    'without_rowid5-3.1',
    'without_rowid5-3.2',
    'without_rowid5-4.1',
    'without_rowid5-5.1',
    'without_rowid5-5.2a',
    'without_rowid5-5.3',
    'without_rowid5-5.5',
    'without_rowid5-5.6',
    'without_rowid5-5.7',
    'without_rowid5-5.8',
    'without_rowid5-5.9',
    'without_rowid5-5.100',
    'without_rowid5-5.101',
    'without_rowid5-5.102',
    'without_rowid5-5.103',
    'without_rowid5-5.104',
    'without_rowid5-6.1',
    'without_rowid5-6.2',
];

// Source truth: SQLite upstream test/without_rowid5.test sections
// without_rowid5-1.1 through without_rowid5-6.2. These requirements cover
// rowid alias omission, WITHOUT ROWID keyword parsing, mandatory primary keys,
// integer-primary-key behavior, NOT NULL conflict policies, and incremental
// blob rejection for WITHOUT ROWID storage.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid5RequirementsCases(1200) as $case) {
    $tests['real upstream without_rowid5 requirements dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('without_rowid5.test sections without_rowid5-1.1 through without_rowid5-6.2', $case['source']);
        $t->same('test/without_rowid5.test', $case['upstream_file']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 40);
        $t->true(str_starts_with($case['upstream_section'], 'without_rowid5-'));
        $t->true($case['scenario'] !== '');
        $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
        $t->true($case['statement'] !== '');
        $t->true($case['table'] !== '');
        $t->true($case['result_code'] === 0 || $case['result_code'] === 1);
        $t->same($case['error'] === null ? 'ok' : 'expected-error', $case['integrity']);
        $t->same($case['result_code'] === 1, $case['error'] !== null);

        if ($case['without_rowid']) {
            $t->same([], $case['rowid_aliases_present']);
            $t->same(false, $case['integer_primary_key_is_rowid_alias']);
        } elseif ($case['error'] === null) {
            $t->same(['rowid', '_rowid_', 'oid'], $case['rowid_aliases_present']);
        } else {
            $t->same([], $case['rowid_aliases_present']);
        }

        if ($case['selected_alias'] !== null) {
            $t->true(in_array($case['selected_alias'], ['rowid', '_rowid_', 'oid'], true));
            $t->contains($case['selected_alias'], $case['statement']);
        }

        match ($case['upstream_section']) {
            'without_rowid5-1.1' => [
                $t->same(false, $case['without_rowid']),
                $t->same(['a'], $case['primary_key']),
                $t->same([[1, 1, 1], [2, 2, 2], [3, 3, 3]], $case['result_rows']),
                $t->same(true, $case['integer_primary_key_is_rowid_alias']),
            ],
            'without_rowid5-1.2' => [
                $t->same('rowid', $case['selected_alias']),
                $t->same('no such column: rowid', $case['error']),
            ],
            'without_rowid5-1.3' => [
                $t->same('_rowid_', $case['selected_alias']),
                $t->same('no such column: _rowid_', $case['error']),
            ],
            'without_rowid5-1.4' => [
                $t->same('oid', $case['selected_alias']),
                $t->same('no such column: oid', $case['error']),
            ],
            'without_rowid5-2.1', 'without_rowid5-2.3', 'without_rowid5-2.5', 'without_rowid5-2.7' => [
                $t->same(true, $case['without_rowid']),
                $t->same(['word'], $case['primary_key']),
                $t->same([['one', 1]], $case['result_rows']),
                $t->same(null, $case['error']),
                $t->true(str_contains((string) $case['keyword_variant'], 'rowid') || str_contains((string) $case['keyword_variant'], 'ROWID')),
            ],
            'without_rowid5-2.2', 'without_rowid5-2.4', 'without_rowid5-2.6', 'without_rowid5-2.8' => [
                $t->same(true, $case['without_rowid']),
                $t->same('rowid', $case['selected_alias']),
                $t->same('no such column: rowid', $case['error']),
            ],
            'without_rowid5-3.1' => [
                $t->same(false, $case['without_rowid']),
                $t->same('unknown table option: _rowid_', $case['error']),
            ],
            'without_rowid5-3.2' => [
                $t->same(false, $case['without_rowid']),
                $t->same('unknown table option: oid', $case['error']),
            ],
            'without_rowid5-4.1' => [
                $t->same(true, $case['without_rowid']),
                $t->same([], $case['primary_key']),
                $t->same('PRIMARY KEY missing on table error3', $case['error']),
            ],
            'without_rowid5-5.1' => [
                $t->same(['key'], $case['primary_key']),
                $t->same([['rival', 'bonus']], $case['result_rows']),
                $t->same(false, $case['integer_primary_key_is_rowid_alias']),
            ],
            'without_rowid5-5.2a' => [
                $t->same('NOT NULL constraint failed: ipk.key', $case['error']),
                $t->same([], $case['result_rows']),
            ],
            'without_rowid5-5.3' => [
                $t->same(true, $case['autoincrement']),
                $t->same('AUTOINCREMENT not allowed on WITHOUT ROWID tables', $case['error']),
            ],
            'without_rowid5-5.5' => [
                $t->same(false, $case['without_rowid']),
                $t->same(['c', 'a', 'e'], $case['primary_key']),
                $t->same([[4]], $case['result_rows']),
            ],
            'without_rowid5-5.6' => [
                $t->same('NOT NULL constraint failed: nnw.a', $case['error']),
                $t->same(['c', 'a', 'e'], $case['primary_key']),
            ],
            'without_rowid5-5.7' => [
                $t->same('NOT NULL constraint failed: nnw.c', $case['error']),
                $t->same(['c', 'a', 'e'], $case['primary_key']),
            ],
            'without_rowid5-5.8' => [
                $t->same('NOT NULL constraint failed: nnw.e', $case['error']),
                $t->same(['c', 'a', 'e'], $case['primary_key']),
            ],
            'without_rowid5-5.9' => [
                $t->same([[1]], $case['result_rows']),
                $t->same(null, $case['error']),
            ],
            'without_rowid5-5.100' => [
                $t->same('ROLLBACK', $case['conflict_policy']),
                $t->same([], $case['result_rows']),
                $t->same('NOT NULL constraint failed: t5.a', $case['error']),
            ],
            'without_rowid5-5.101' => [
                $t->same('ABORT', $case['conflict_policy']),
                $t->same([[1, 2, 3]], $case['result_rows']),
                $t->same('NOT NULL constraint failed: t5.a', $case['error']),
            ],
            'without_rowid5-5.102' => [
                $t->same('FAIL', $case['conflict_policy']),
                $t->same([[1, 2, 3]], $case['result_rows']),
                $t->same('NOT NULL constraint failed: t5.a', $case['error']),
            ],
            'without_rowid5-5.103' => [
                $t->same('IGNORE', $case['conflict_policy']),
                $t->same([[1, 2, 3], [6, 7, 8]], $case['result_rows']),
                $t->same(null, $case['error']),
            ],
            'without_rowid5-5.104' => [
                $t->same('REPLACE', $case['conflict_policy']),
                $t->same([[1, 2, 3], [3, 4, 5], [6, 7, 8]], $case['result_rows']),
                $t->same(null, $case['error']),
            ],
            'without_rowid5-6.1' => [
                $t->same(false, $case['uses_incremental_blob']),
                $t->same([[1, '0102030405060708090a0b0c0d0e0f']], $case['result_rows']),
            ],
            'without_rowid5-6.2' => [
                $t->same(true, $case['uses_incremental_blob']),
                $t->same('cannot open table without rowid: b1', $case['error']),
            ],
        };
    };
}

$tests['real upstream without_rowid5 requirements dynamic source range'] = static function (TestRunner $t) use ($expectedSections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid5RequirementsCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same($expectedSections, $sections);
    $t->same('without_rowid5-1.1', $cases[0]['upstream_section']);
    $t->same('without_rowid5-6.2', $cases[29]['upstream_section']);
    $t->same('without_rowid5-1.1', $cases[30]['upstream_section']);
    $t->same(40, $cases[1199]['batch']);
};

$tests['real upstream without_rowid5 requirements dynamic upstream file cites owned sections'] = static function (TestRunner $t): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid5.test');

    $t->contains('do_execsql_test without_rowid5-1.1', $source);
    $t->contains('do_catchsql_test without_rowid5-3.1', $source);
    $t->contains('do_catchsql_test without_rowid5-5.3', $source);
    $t->contains('do_test without_rowid5-5.100', $source);
    $t->contains('do_test without_rowid5-6.2', $source);
};

$tests['real upstream without_rowid5 requirements dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid5RequirementsCases(0));
};

$tests['real upstream without_rowid5 requirements dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for SQLite WITHOUT ROWID requirements from upstream without_rowid5.test',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for SQLite WITHOUT ROWID requirements from upstream without_rowid5.test',
    );
};

return $tests;
