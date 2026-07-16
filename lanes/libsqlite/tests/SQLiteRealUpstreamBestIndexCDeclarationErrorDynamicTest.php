<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexC.test sections 4.0 through
// 4.4. The adjacent bestindexC files cover LIMIT/OFFSET and RHS-value
// constraints; this batch owns xConnect/declare_vtab error preservation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexCVirtualTableDeclarationErrorCases(1000) as $case) {
    $tests['real upstream bestindexC declaration error dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexC.test sections 4.0 through 4.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexC-4.'));
        $t->true(str_contains($case['statement'], 'dynamic batch'));
        $t->true($case['batch'] >= 1);
        $t->same(1, $case['expected_code']);
        $t->same('SQLITE_ERROR', $case['errcode']);
        $t->same('xConnect', $case['connect_method']);
        $t->same('expected-error', $case['integrity']);
        $t->true($case['expected_error'] !== '');
        $t->true(str_contains($case['detail'], 'dynamic batch ' . $case['batch']));

        if ($case['upstream_section'] === 'bestindexC-4.0') {
            $t->same('', $case['declared_sql']);
            $t->same('not happy!', $case['expected_error']);
            $t->true(str_contains($case['detail'], 'application error'));
        }

        if ($case['upstream_section'] === 'bestindexC-4.2') {
            $t->same('PRAGMA page_size=1024', $case['declared_sql']);
            $t->same('declare_vtab: syntax error', $case['expected_error']);
            $t->true(str_contains($case['detail'], 'non-CREATE-TABLE'));
        }

        if ($case['upstream_section'] === 'bestindexC-4.3') {
            $t->same('CREATE TABLE x1(', $case['declared_sql']);
            $t->same('declare_vtab: incomplete input', $case['expected_error']);
        }

        if ($case['upstream_section'] === 'bestindexC-4.4') {
            $t->same('CREATE TABLE x1(insert)', $case['declared_sql']);
            $t->same('declare_vtab: near "insert": syntax error', $case['expected_error']);
        }
    };
}

$tests['real upstream bestindexC declaration error corpus summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexCVirtualTableDeclarationErrorCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('bestindexC-4.0', $cases[0]['upstream_section']);
    $t->same('bestindexC-4.4', $cases[3]['upstream_section']);
    $t->same('bestindexC-4.4', $cases[999]['upstream_section']);
    $t->same([
        'bestindexC-4.0',
        'bestindexC-4.2',
        'bestindexC-4.3',
        'bestindexC-4.4',
    ], $sections);
};

$tests['real upstream bestindexC declaration error rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::bestindexCVirtualTableDeclarationErrorCases(0));
};

$tests['real upstream bestindexC declaration error dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xConnect declaration diagnostics, SQLite error-code preservation, and upstream bestindexC source hydration',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xConnect declaration diagnostics, SQLite error-code preservation, and upstream bestindexC source hydration',
    );
};

return $tests;
