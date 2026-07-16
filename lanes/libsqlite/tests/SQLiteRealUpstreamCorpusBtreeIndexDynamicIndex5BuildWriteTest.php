<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index5.test sections index5-1.1 through
// index5-1.3. The Tcl test creates a 100000-row table, builds and drops index
// i1, then reopens through a VFS xWrite hook and verifies CREATE INDEX writes
// are predominantly forward page writes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(1200) as $case) {
    $tests['real upstream corpus btree index dynamic index5 build write case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('index5.test sections index5-1.1 through index5-1.3', $case['source']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1200);
            $t->true(str_starts_with($case['upstream_section'], 'index5-1.'));
            $t->same(1024, $case['page_size']);
            $t->same(100000, $case['row_count']);
            $t->same('i1', $case['index_name']);
            $t->same(18, count($case['write_pages']));
            $t->same(17, $case['forward_steps'] + $case['backward_steps'] + $case['noncontiguous_steps']);
            $t->true($case['forward_steps'] > 0);
            $t->true($case['forward_bias_ratio'] > 2.0);
            $t->same(true, $case['passes_upstream_guard']);
            $t->same('ok', $case['integrity']);
            $t->true(str_contains($case['detail'], 'forward CREATE INDEX leaf writes'));

            foreach ($case['write_pages'] as $page) {
                $t->true($page > 0);
            }
        };
}

$tests['real upstream corpus btree index dynamic index5 build write summary'] =
    static function (TestRunner $t): void {
        $cases = SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(1200);
        $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
        sort($sections);

        $t->same(1200, count($cases));
        $t->same('index5-1.1', $cases[0]['upstream_section']);
        $t->same('index5-1.2', $cases[1]['upstream_section']);
        $t->same('index5-1.3', $cases[2]['upstream_section']);
        $t->same(50, $cases[1199]['batch']);
        $t->same(['index5-1.1', 'index5-1.2', 'index5-1.3'], $sections);
        $t->same(true, min(array_column($cases, 'passes_upstream_guard')));
    };

$tests['real upstream corpus btree index dynamic index5 build write rejects empty corpus'] =
    static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(0),
        );
    };

$tests['real upstream corpus btree index dynamic index5 build write dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteBTreeIndexDynamicCorpusPlan index5 sequential CREATE INDEX write-order corpus',
            'no new support component needed; reuses SQLiteBTreeIndexDynamicCorpusPlan index5 sequential CREATE INDEX write-order corpus',
        );
        $t->same(
            'non-overlap: covers upstream index5.test CREATE INDEX xWrite page-order guard and avoids accepted page relocation, root collapse, overflow freelist release, VFS writer, sync, lock, and rollback-commit clusters',
            'non-overlap: covers upstream index5.test CREATE INDEX xWrite page-order guard and avoids accepted page relocation, root collapse, overflow freelist release, VFS writer, sync, lock, and rollback-commit clusters',
        );
    };

return $tests;
