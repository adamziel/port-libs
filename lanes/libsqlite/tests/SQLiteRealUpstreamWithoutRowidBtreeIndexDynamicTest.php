<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/without_rowid6.test sections
// without_rowid6-100 through 600 and test/without_rowid7.test sections 1.0
// through 3.6. These cases preserve WITHOUT ROWID primary-key b-tree
// de-duplication, autoindex metadata, collation, and expected error behavior.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidRedundantPrimaryKeyCases(1000) as $case) {
    $tests['real upstream without rowid btree index dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->same('without_rowid6.test', $case['source']);
        $t->same('without_rowid6-100/140', $case['upstream_section']);
        $t->true($case['scenario'] !== '');
        $t->same('t1', $case['table']);
        $t->same(['a', 'b', 'c', 'd'], $case['primary_key']);
        $t->same(['a', 'b', 'c', 'a', 'b', 'c', 'd', 'a', 'b', 'c'], $case['redundant_primary_key']);
        $t->same([['b', 'b']], $case['unique_constraints']);
        $t->true($case['query'] !== '');
        $t->true($case['detail'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same(null, $case['error']);
        $t->true($case['target_a'] >= 1 && $case['target_a'] <= 1000);
        $t->same($case['target_a'] + 1000, $case['target_b']);
        $t->same('x' . $case['target_a'] . 'y', $case['expected_c']);
        $t->same([[$case['expected_c']], [$case['expected_c']]], $case['result_rows']);
        $t->true(str_contains($case['query'], 'a=' . $case['target_a']));
        $t->true(str_contains($case['query'], 'b=' . $case['target_b']));
        $t->true(str_contains($case['detail'], 'PRIMARY KEY'));
        $t->true(str_contains($case['detail'], 'USING INDEX t1a'));

        foreach ($case['index_info'] as $entry) {
            $t->true($entry['seqno'] >= 0);
            $t->true($entry['cid'] >= 0);
            $t->true($entry['name'] !== '');
            $t->true($entry['key'] === 0 || $entry['key'] === 1);
            $t->true($entry['collation'] !== '');
        }

        foreach ($case['index_list'] as $entry) {
            $t->true(str_starts_with($entry['name'], 'sqlite_autoindex_') || $entry['name'] === 't1a');
            $t->true($entry['unique'] === 0 || $entry['unique'] === 1);
            $t->true($entry['origin'] === 'pk' || $entry['origin'] === 'c');
        }

        $t->same('sqlite_autoindex_t1_1', $case['index_list'][0]['name']);
        $t->same('t1a', $case['index_list'][1]['name']);
        $t->same(1, $case['index_list'][0]['unique']);
        $t->same(0, $case['index_list'][1]['unique']);
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidRedundantPrimaryKeySectionCases() as $case) {
    $tests['real upstream without rowid btree index section ' . $case['upstream_section']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['source'] === 'without_rowid6.test' || $case['source'] === 'without_rowid7.test');
        $t->true(str_starts_with($case['upstream_section'], 'without_rowid6-') || str_starts_with($case['upstream_section'], 'without_rowid7-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['table'] !== '');
        $t->true($case['primary_key'] !== []);
        $t->true($case['redundant_primary_key'] !== []);
        $t->true(count($case['primary_key']) <= count($case['redundant_primary_key']));
        $t->true($case['query'] !== '');
        $t->true($case['detail'] !== '');
        $t->same($case['error'] === null ? 'ok' : 'expected-error', $case['integrity']);
        if ($case['upstream_section'] === 'without_rowid6-200/220' || $case['upstream_section'] === 'without_rowid6-300/320') {
            $t->same(['b'], $case['primary_key']);
            $t->same('sqlite_autoindex_t1_2', $case['index_list'][0]['name']);
            $t->same([[4], [1]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'PRIMARY KEY'));
        }

        if ($case['upstream_section'] === 'without_rowid6-500/520') {
            $t->same(['b', 'c'], $case['primary_key']);
            $t->same('sqlite_autoindex_t1_1', $case['index_list'][0]['name']);
            $t->same(['b', 'c'], $case['unique_constraints'][0]);
        }

        if ($case['upstream_section'] === 'without_rowid6-600') {
            $t->same('no such column: rowid', $case['error']);
            $t->same([], $case['index_info']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'without_rowid7-1.0/1.1') {
            $t->same(['a', 'b'], $case['primary_key']);
            $t->same('NOCASE', $case['index_info'][1]['collation']);
            $t->same('UNIQUE constraint failed: t1.a, t1.b', $case['error']);
        }

        if ($case['upstream_section'] === 'without_rowid7-2.0/2.4') {
            $t->same(['a', 'a'], $case['primary_key']);
            $t->same('NOCASE', $case['index_info'][0]['collation']);
            $t->same('BINARY', $case['index_info'][1]['collation']);
            $t->same([['one']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'without_rowid7-3.1/3.6') {
            $t->same('mysort', $case['index_info'][0]['collation']);
            $t->same('mysort2', $case['index_info'][1]['collation']);
            $t->same('no such collation sequence', $case['error']);
        }
    };
}

return $tests;
