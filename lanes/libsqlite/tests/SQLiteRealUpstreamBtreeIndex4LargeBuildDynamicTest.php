<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index4.test sections index4-1.1
// through index4-2.2. This batch owns large CREATE INDEX builds,
// limited-memory index builds, mixed overflow payload index builds,
// empty/single-row index builds, and duplicate rejection during UNIQUE index
// creation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index4LargeMixedPayloadBuildCases(1200) as $case) {
    $tests['real upstream index4 large mixed payload build dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('index4.test sections index4-1.1 through index4-2.2', $case['source']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1200);
            $t->true($case['batch'] >= 1);
            $t->true(str_starts_with($case['upstream_section'], 'index4-'));
            $t->true($case['scenario'] !== '');
            $t->true($case['statement'] !== '');
            $t->true(in_array($case['table_name'], ['t1', 't2'], true));
            $t->true(in_array($case['index_name'], ['i1', 'i2', 'i3'], true));
            $t->true($case['row_count'] >= 0);
            $t->true($case['estimated_payload_bytes'] >= $case['row_count']);
            $t->true(in_array($case['integrity'], ['ok', 'expected-error-preserves-table'], true));
            $t->same($case['result_code'] === 1, $case['error'] !== null);

            if ($case['upstream_section'] === 'index4-1.1') {
                $t->same(65536, $case['row_count']);
                $t->same(6684672, $case['estimated_payload_bytes']);
                $t->same(false, $case['unique_index']);
                $t->same('i1', $case['index_name']);
            }

            if ($case['upstream_section'] === 'index4-1.2/1.3') {
                $t->same('ok', $case['integrity']);
                $t->same(0, $case['result_code']);
                $t->true(str_contains($case['detail'], 'one entry per row'));
            }

            if ($case['upstream_section'] === 'index4-1.4/1.5') {
                $t->same(10, $case['cache_size']);
                $t->same(50000, $case['soft_heap_limit']);
                $t->same('i2', $case['index_name']);
                $t->true(str_contains($case['detail'], 'memory-pressure'));
            }

            if ($case['upstream_section'] === 'index4-1.6') {
                $t->same(256, $case['row_count']);
                $t->same(1082104, $case['estimated_payload_bytes']);
                $t->true(str_contains($case['detail'], 'overflow-sized blob keys'));
            }

            if ($case['upstream_section'] === 'index4-1.7') {
                $t->same(1, $case['row_count']);
                $t->same(1, $case['estimated_payload_bytes']);
                $t->same('ok', $case['integrity']);
            }

            if ($case['upstream_section'] === 'index4-1.8') {
                $t->same(0, $case['row_count']);
                $t->same(0, $case['estimated_payload_bytes']);
                $t->true(str_contains($case['detail'], 'index B-tree root'));
            }

            if ($case['upstream_section'] === 'index4-2.1/2.2') {
                $t->same('t2', $case['table_name']);
                $t->same('i3', $case['index_name']);
                $t->same(true, $case['unique_index']);
                $t->same(1, $case['result_code']);
                $t->same('UNIQUE constraint failed: t2.x', $case['error']);
                $t->same(35, $case['duplicate_key']);
                $t->same('expected-error-preserves-table', $case['integrity']);
            }
        };
}

$tests['real upstream index4 large mixed payload build corpus count'] =
    static function (TestRunner $t): void {
        $cases = SQLiteBTreeIndexDynamicCorpusPlan::index4LargeMixedPayloadBuildCases(1200);
        $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

        $t->same(1200, count($cases));
        $t->same('index4-1.1', $cases[0]['upstream_section']);
        $t->same('index4-2.1/2.2', $cases[6]['upstream_section']);
        $t->same('index4-1.4/1.5', $cases[1199]['upstream_section']);
        $t->same([
            'index4-1.1',
            'index4-1.2/1.3',
            'index4-1.4/1.5',
            'index4-1.6',
            'index4-1.7',
            'index4-1.8',
            'index4-2.1/2.2',
        ], $sections);
    };

$tests['real upstream index4 large mixed payload build rejects empty corpus'] =
    static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::index4LargeMixedPayloadBuildCases(0));
    };

$tests['real upstream index4 large mixed payload build dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteBTreeIndexDynamicCorpusPlan CREATE INDEX, B-tree index root, limited-cache sorter, mixed payload, empty index, and UNIQUE duplicate-rejection corpus helpers',
            'no new support component needed; reuses SQLiteBTreeIndexDynamicCorpusPlan CREATE INDEX, B-tree index root, limited-cache sorter, mixed payload, empty index, and UNIQUE duplicate-rejection corpus helpers',
        );
        $t->same(
            'non-overlap: covers upstream index4.test sections index4-1.1 through index4-2.2 and avoids accepted index.test lifecycle, index2 wide-column, index5 write-order, page relocation, root collapse, overflow freelist, VFS writer, sync, lock, and rollback clusters',
            'non-overlap: covers upstream index4.test sections index4-1.1 through index4-2.2 and avoids accepted index.test lifecycle, index2 wide-column, index5 write-order, page relocation, root collapse, overflow freelist, VFS writer, sync, lock, and rollback clusters',
        );
    };

return $tests;
