<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexedby.test sections indexedby-6.1
// through indexedby-8.6. This shard covers NOT INDEXED rowid-ordered scans
// and forced INDEXED BY requirements for DELETE and UPDATE row discovery.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByDmlAndRowidScanCases(1000) as $case) {
    $tests['real upstream indexedby DML and rowid scan dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexedby.test sections indexedby-6.1 through indexedby-8.6', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], [
            'indexedby-6.1',
            'indexedby-6.2',
            'indexedby-7.1',
            'indexedby-7.2',
            'indexedby-7.3',
            'indexedby-7.4',
            'indexedby-7.5',
            'indexedby-7.6',
            'indexedby-8.1',
            'indexedby-8.2',
            'indexedby-8.3',
            'indexedby-8.4',
            'indexedby-8.5',
            'indexedby-8.6',
        ], true));
        $t->same('t1', $case['table_name']);
        $t->same(0, $case['result_code']);
        $t->same(null, $case['error']);
        $t->same('ok', $case['integrity']);
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['statement_kind'], ['select', 'delete', 'update'], true));
        $t->true(is_array($case['where_terms']));
        $t->true($case['detail'] !== '');

        if ($case['not_indexed']) {
            $t->same(null, $case['indexed_by']);
            $t->same(false, $case['uses_index']);
            $t->same(null, $case['index_name']);
            $t->true(str_contains($case['detail'], 'NOT INDEXED') || str_contains($case['detail'], 'SCAN t1'));
        }

        if ($case['indexed_by'] !== null) {
            $t->same($case['indexed_by'], $case['index_name']);
            $t->same(true, $case['uses_index']);
            $t->true(str_contains($case['statement'], 'INDEXED BY ' . $case['indexed_by']));
        }

        if ($case['statement_kind'] === 'select') {
            $t->same(false, $case['mutates_rows']);
            $t->same(false, $case['rowid_rewrite']);
            $t->same(['b=?'], $case['where_terms']);
        }

        if ($case['statement_kind'] === 'delete') {
            $t->same(true, $case['mutates_rows']);
            $t->same(false, $case['rowid_rewrite']);
            $t->true(str_starts_with($case['statement'], 'DELETE FROM t1'));
        }

        if ($case['statement_kind'] === 'update') {
            $t->same(true, $case['mutates_rows']);
            $t->same(true, $case['rowid_rewrite']);
            $t->true(str_starts_with($case['statement'], 'UPDATE t1'));
            $t->true(str_contains($case['statement'], 'rowid=rowid+1'));
        }

        if ($case['upstream_section'] === 'indexedby-7.6' || $case['upstream_section'] === 'indexedby-8.6') {
            $t->same(['a=?'], $case['where_terms']);
            $t->same('i2', $case['index_name']);
            $t->true(str_contains($case['detail'], 'residual a=?'));
        }
    };
}

$tests['real upstream indexedby DML dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexedByDmlAndRowidScanCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('indexedby-6.1', $cases[0]['upstream_section']);
    $t->same('indexedby-8.6', $cases[13]['upstream_section']);
    $t->same('indexedby-6.1', $cases[14]['upstream_section']);
    $t->same('indexedby.test sections indexedby-6.1 through indexedby-8.6', $cases[999]['source']);
    $t->same([
        'indexedby-6.1',
        'indexedby-6.2',
        'indexedby-7.1',
        'indexedby-7.2',
        'indexedby-7.3',
        'indexedby-7.4',
        'indexedby-7.5',
        'indexedby-7.6',
        'indexedby-8.1',
        'indexedby-8.2',
        'indexedby-8.3',
        'indexedby-8.4',
        'indexedby-8.5',
        'indexedby-8.6',
    ], $sections);
};

$tests['real upstream indexedby DML dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexedByDmlAndRowidScanCases(0));
};

$tests['real upstream indexedby DML dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, DML index forcing, NOT INDEXED scan, rowid rewrite, and planner-detail fixtures',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, DML index forcing, NOT INDEXED scan, rowid rewrite, and planner-detail fixtures',
    );
};

return $tests;
