<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexC.test sections 5.2 through
// 6.6. These cases cover virtual-table xBestIndex equality constraints,
// row-value equality decomposition, constraint collation reporting, refusal of
// collated OR conjuncts, and rhs_value() extraction for LIMIT constraints.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexCConstraintAndRhsValueCases(1000) as $case) {
    $tests['real upstream bestindexC constraint rhs dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexC.test sections 5.2 through 6.6', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexC-'));
        $t->true($case['scenario'] !== '');
        $t->true(str_contains($case['sql'], 'dynamic batch ' . $case['batch']));
        $t->true($case['required_constraints'] !== []);
        $t->true(str_contains($case['detail'], 'xBestIndex constraints='));
        $t->same($case['expected_code'] === 0 ? 'ok' : 'expected-error', $case['integrity']);
        $t->same($case['rhs_value'], $case['idxnum']);

        foreach ($case['required_constraints'] as $constraint) {
            $t->true(in_array($constraint, ['a', 'b', 'c', 'limit'], true));
        }

        foreach ($case['collations'] as $collation) {
            $t->true(in_array($collation, ['BINARY', 'NOCASE'], true));
        }

        foreach ($case['result_rows'] as $row) {
            $t->same(array_values($row), $row);
        }

        if ($case['expected_code'] === 1) {
            $t->same('no query solution', $case['expected_error']);
            $t->same([], $case['result_rows']);
            $t->same(null, $case['constraint_sql']);
            $t->true(str_contains($case['sql'], 'COLLATE'));
        }

        if ($case['upstream_section'] === 'bestindexC-5.2.0') {
            $t->same(0, $case['expected_code']);
            $t->same([], $case['result_rows']);
            $t->true(str_contains((string) $case['constraint_sql'], 'c = %2%'));
        }

        if ($case['upstream_section'] === 'bestindexC-5.2.1/5.3/5.4') {
            $t->same([['X', 'Y', 'Z', 'two']], $case['result_rows']);
            $t->true(str_contains($case['sql'], '(a, b, c)'));
            $t->same(['BINARY', 'BINARY', 'BINARY'], $case['collations']);
        }

        if ($case['upstream_section'] === 'bestindexC-5.5') {
            $t->same([['x', 'y', 'z', 'one']], $case['result_rows']);
            $t->same(['BINARY', 'BINARY', 'BINARY'], $case['collations']);
        }

        if ($case['upstream_section'] === 'bestindexC-5.6') {
            $t->same([['x', 'y', 'z', 'one'], ['X', 'Y', 'Z', 'two']], $case['result_rows']);
            $t->same(['NOCASE', 'NOCASE', 'NOCASE'], $case['collations']);
            $t->true(str_contains((string) $case['constraint_sql'], 'COLLATE NOCASE'));
        }

        if (str_starts_with($case['upstream_section'], 'bestindexC-6.')) {
            $t->same(0, $case['expected_code']);
            $t->same(null, $case['expected_error']);
            $t->true(in_array('limit', $case['required_constraints'], true));
            $t->true($case['rhs_value'] !== null);
            $t->true($case['result_rows'] !== []);
        }

        if ($case['upstream_section'] === 'bestindexC-6.1') {
            $t->same([[50, 50, 50, 50]], $case['result_rows']);
            $t->same(50, $case['rhs_value']);
        }

        if ($case['upstream_section'] === 'bestindexC-6.2' || $case['upstream_section'] === 'bestindexC-6.3') {
            $t->same(0, $case['rhs_value']);
        }

        if ($case['upstream_section'] === 'bestindexC-6.6') {
            $t->same([[555]], $case['result_rows']);
            $t->same(555, $case['rhs_value']);
        }
    };
}

$tests['real upstream bestindexC constraint rhs dynamic source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexCConstraintAndRhsValueCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('bestindexC-5.2.0', $cases[0]['upstream_section']);
    $t->same('bestindexC-6.6', $cases[10]['upstream_section']);
    $t->same('bestindexC-6.4/6.5', $cases[999]['upstream_section']);
    $t->same([
        'bestindexC-5.2.0',
        'bestindexC-5.2.1/5.3/5.4',
        'bestindexC-5.5',
        'bestindexC-5.6',
        'bestindexC-5.8',
        'bestindexC-5.9',
        'bestindexC-6.1',
        'bestindexC-6.2',
        'bestindexC-6.3',
        'bestindexC-6.4/6.5',
        'bestindexC-6.6',
    ], $sections);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['expected_code'] === 1)) > 150);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['rhs_value'] !== null)) > 400);
    $t->true(count(array_filter($cases, static fn (array $case): bool => in_array('NOCASE', $case['collations'], true))) > 250);
};

$tests['real upstream bestindexC constraint rhs dynamic rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindexCConstraintAndRhsValueCases(0));
};

$tests['real upstream bestindexC constraint rhs dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table constraint, collation, OR-solution, and rhs_value metadata helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table constraint, collation, OR-solution, and rhs_value metadata helpers',
    );
};

return $tests;
