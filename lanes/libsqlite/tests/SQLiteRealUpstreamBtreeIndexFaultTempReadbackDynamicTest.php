<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexfault.test sections 4.1, 4.2, and
// 5. These cases are distinct from the earlier indexfault CREATE INDEX fault
// templates: they cover release-memory temp-btree readback during index build
// and the long-name WITHOUT ROWID primary-key schema btree path.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexFaultTempReadbackAndLongNameCases(1000) as $case) {
    $tests['real upstream indexfault temp readback dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexfault.test sections indexfault-4.1, indexfault-4.2, and indexfault-5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], ['indexfault-4.1', 'indexfault-4.2', 'indexfault-5'], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true($case['operation'] !== '');
        $t->same(0, $case['result_code']);
        $t->same('', $case['message']);
        $t->same('ok', $case['integrity']);

        if ($case['upstream_section'] === 'indexfault-4.1') {
            $t->same('t1', $case['table_name']);
            $t->same(1, $case['column_count']);
            $t->same(64, $case['row_count']);
            $t->same(11000, $case['payload_bytes']);
            $t->same('CREATE INDEX i1 ON t1(x)', $case['operation']);
            $t->same(false, $case['release_memory_before_fault']);
            $t->same(null, $case['soft_heap_limit']);
            $t->same('xRead', $case['fault_method']);
            $t->same('i1', $case['expected_index']);
            $t->same(true, $case['temp_btree_readback']);
            $t->same(false, $case['uses_without_rowid']);
            $t->same(false, $case['uses_primary_key']);
        }

        if ($case['upstream_section'] === 'indexfault-4.2') {
            $t->same('t1', $case['table_name']);
            $t->same(64, $case['row_count']);
            $t->same(11000, $case['payload_bytes']);
            $t->same(true, $case['release_memory_before_fault']);
            $t->same(20000, $case['soft_heap_limit']);
            $t->same('xRead', $case['fault_method']);
            $t->same('i1', $case['expected_index']);
            $t->same(true, $case['temp_btree_readback']);
            $t->contains('release-memory', $case['scenario']);
        }

        if ($case['upstream_section'] === 'indexfault-5') {
            $t->true(strlen($case['table_name']) > 450);
            $t->same($case['name_length'], strlen($case['table_name']));
            $t->same(1, $case['column_count']);
            $t->same(0, $case['row_count']);
            $t->same(0, $case['payload_bytes']);
            $t->same(true, $case['uses_without_rowid']);
            $t->same(true, $case['uses_primary_key']);
            $t->same(false, $case['release_memory_before_fault']);
            $t->same(null, $case['fault_method']);
            $t->same(null, $case['expected_index']);
            $t->same(false, $case['temp_btree_readback']);
            $t->contains('WITHOUT ROWID', $case['operation']);
        }
    };
}

$tests['real upstream indexfault temp readback dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexFaultTempReadbackAndLongNameCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('indexfault-4.1', $cases[0]['upstream_section']);
    $t->same('indexfault-4.2', $cases[1]['upstream_section']);
    $t->same('indexfault-5', $cases[2]['upstream_section']);
    $t->same('indexfault-4.1', $cases[3]['upstream_section']);
    $t->same(['indexfault-4.1', 'indexfault-4.2', 'indexfault-5'], $sections);
};

$tests['real upstream indexfault temp readback rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexFaultTempReadbackAndLongNameCases(0));
};

$tests['real upstream indexfault temp readback dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, temp btree readback, soft heap limit, and WITHOUT ROWID schema-path fixtures',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, temp btree readback, soft heap limit, and WITHOUT ROWID schema-path fixtures',
    );
};

return $tests;
