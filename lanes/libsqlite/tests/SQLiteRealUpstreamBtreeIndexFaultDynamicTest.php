<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexfault.test sections 1.1, 2.1,
// 2.2, 3.1, and 3.3. The Tcl script builds indexes under malloc, xOpen, and
// xWrite fault injection and verifies either successful CREATE INDEX or clean
// rollback with integrity preserved. These dynamic cases keep each injected
// attempt distinct without adding runner metadata rows.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexFaultCreateIndexCases(1000) as $case) {
    $tests['real upstream indexfault create index fault dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexfault.test sections 1.1, 2.1, 2.2, 3.1, and 3.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream'], $case['section'] . '.dynamic-fault-'));
        $t->true(in_array($case['section'], ['indexfault-1.1', 'indexfault-2.1', 'indexfault-2.2', 'indexfault-3.1', 'indexfault-3.3'], true));
        $t->true($case['table_shape'] !== '');
        $t->true($case['index_columns'] !== []);
        $t->true($case['row_count'] === 128 || $case['row_count'] === 256 || $case['row_count'] === 512);
        $t->true($case['blob_bytes'] === 30 || $case['blob_bytes'] === 202 || $case['blob_bytes'] === 11000);
        $t->same($case['row_count'], $case['row_count_preserved']);
        $t->same('ok', $case['integrity']);
        $t->true($case['injection_point'] >= 1 && $case['injection_point'] <= 200);
        $t->true($case['fault_filter'] !== []);
        $t->true($case['fault_target'] !== '');
        $t->same($case['result_code'] === 0, $case['error'] === null);
        $t->same($case['result_code'] === 0, $case['index_created']);
        $t->same($case['result_code'] !== 0, $case['expected_retryable']);

        if ($case['expected_retryable']) {
            $t->same(1, $case['result_code']);
            $t->same('disk I/O error', $case['error']);
            $t->same(false, $case['index_created']);
        } else {
            $t->same(0, $case['result_code']);
            $t->same(null, $case['error']);
            $t->same(true, $case['index_created']);
        }

        if ($case['section'] === 'indexfault-2.2') {
            $t->same(50000, $case['soft_heap_limit']);
            $t->same(true, $case['temp_btree_spilled']);
        }

        if ($case['section'] === 'indexfault-3.1') {
            $t->same(['xOpen'], $case['fault_filter']);
            $t->same('temporary sorter open', $case['fault_target']);
        }

        if ($case['section'] === 'indexfault-3.3') {
            $t->same(['xOpen', 'xWrite'], $case['fault_filter']);
            $t->same('second temporary file write', $case['fault_target']);
        }
    };
}

return $tests;
