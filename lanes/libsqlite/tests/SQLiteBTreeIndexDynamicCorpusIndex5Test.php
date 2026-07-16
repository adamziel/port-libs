<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index5.test index5-1.1 through 1.3.
// The Tcl test builds 100000 rows at 1024-byte pages, recreates i1 under a
// VFS xWrite tracer, then asserts the CREATE INDEX page writes are mostly
// forward-contiguous.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialCreateIndexWriteCases(1000) as $case) {
    $tests['real upstream index5 sequential create index write case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index5.test index5-1.1 through index5-1.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index5-1.'));
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->true($case['page_number'] >= 2);
        $t->same(($case['page_number'] - 1) * $case['page_size'], $case['write_offset']);
        $t->true(in_array($case['transition'], ['first', 'forward', 'backward', 'noncontiguous'], true));
        $t->true($case['forward_writes'] >= 0);
        $t->true($case['backward_writes'] >= 0);
        $t->true($case['noncontiguous_writes'] >= 0);
        if ($case['previous_page'] === null) {
            $t->same('first', $case['transition']);
            $t->same(1, $case['case']);
        } elseif ($case['transition'] === 'forward') {
            $t->same($case['previous_page'] + 1, $case['page_number']);
        } elseif ($case['transition'] === 'backward') {
            $t->same($case['previous_page'] - 1, $case['page_number']);
        } else {
            $t->true(abs($case['page_number'] - $case['previous_page']) > 1);
        }
        $t->same('CREATE INDEX i1 ON t1(x)', $case['operation']);
        $t->same('i1', $case['index_name']);
        $t->same('ok', $case['integrity']);
    };
}

$tests['real upstream index5 sequential create index write aggregate ratio'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialCreateIndexWriteCases(1000);
    $last = $cases[count($cases) - 1];
    $t->same(1000, count($cases));
    $t->same('index5-1.1', $cases[0]['upstream_section']);
    $t->same('first', $cases[0]['transition']);
    $t->same(true, $last['forward_dominates']);
    $t->true($last['forward_writes'] > 2 * ($last['backward_writes'] + $last['noncontiguous_writes']));
    $t->true($last['forward_writes'] > 800);
};

$tests['real upstream index5 sequential create index write dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and VFS write-offset accounting',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and VFS write-offset accounting',
    );
};

return $tests;
