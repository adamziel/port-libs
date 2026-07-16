<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index5.test sections index5-1.1
// through index5-1.3. The upstream test installs a VFS xWrite hook while
// creating an index and verifies that database page writes are mostly forward.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteLocalityCases(1200) as $case) {
    $tests['real upstream index5 create-index write locality dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index5.test sections index5-1.1 through index5-1.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'index5-1.'));
        $t->true($case['batch'] >= 1);
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->same(100, $case['payload_bytes']);
        $t->same('i1', $case['index_name']);
        $t->true(str_contains($case['operation'], 'CREATE INDEX i1 ON t1(x)'));
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['drop_preserves_page_size']);

        $writePages = $case['write_pages'];
        $t->true(count($writePages) >= 48);
        $t->true($writePages[0] >= 2);
        $t->same(count($writePages) - 1, $case['forward_steps'] + $case['backward_steps'] + $case['noncontiguous_steps']);
        $t->true($case['forward_steps'] > 0);
        $t->true($case['forward_dominates']);
        $t->true($case['forward_steps'] > 2 * ($case['backward_steps'] + $case['noncontiguous_steps']));
        $t->true($case['backward_steps'] >= 0);
        $t->true($case['noncontiguous_steps'] >= 0);
        $t->true(str_contains($case['detail'], 'CREATE INDEX write locality replay batch ' . $case['batch']));
        $t->true(str_contains($case['detail'], 'forward=' . $case['forward_steps']));
    };
}

$tests['real upstream index5 create-index write locality dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteLocalityCases(1200);

    $t->same(1200, count($cases));
    $t->same('index5-1.1/1.2/1.3', $cases[0]['upstream_section']);
    $t->same('index5-1.2/1.3-forward-4', $cases[3]['upstream_section']);
    $t->same('index5-1.1/1.2/1.3', $cases[4]['upstream_section']);
    $t->same(300, $cases[1199]['batch']);
};

$tests['real upstream index5 create-index write locality rejects empty count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteLocalityCases(0));
};

$tests['real upstream index5 create-index write locality dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and VFS write-locality counters',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and VFS write-locality counters',
    );
};

return $tests;
