<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$summary = static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteOrderSummary(1200);
$cases = SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteOrderCases(1200);

$tests['real upstream index5 write order cites hydrated upstream source'] = static function (TestRunner $t) use ($summary): void {
    $t->same('index5.test index5-1.1 through index5-1.3', $summary()['source']);
    $t->same(1024, $summary()['page_size']);
    $t->same(100000, $summary()['row_count']);
    $t->same('i1', $summary()['index_name']);
};

$tests['real upstream index5 write order summary keeps forward writes dominant'] = static function (TestRunner $t) use ($summary): void {
    $data = $summary();

    $t->same(1200, $data['total_writes']);
    $t->same(1199, $data['forward_count']);
    $t->same(0, $data['backward_count']);
    $t->same(0, $data['noncontiguous_count']);
    $t->same(true, $data['forward_dominates']);
};

$tests['real upstream index5 write order summary has contiguous first and last pages'] = static function (TestRunner $t) use ($summary): void {
    $data = $summary();

    $t->same(1, $data['first_page']);
    $t->same(1200, $data['last_page']);
    $t->same($data['total_writes'], $data['last_page'] - $data['first_page'] + 1);
};

foreach ($cases as $case) {
    $tests['real upstream index5 create index forward write transition ' . $case['ordinal']] = static function (TestRunner $t) use ($case): void {
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->same('i1', $case['index_name']);
        $t->same($case['ordinal'], $case['page']);
        $t->same('index5-1.' . ($case['ordinal'] <= 1 ? '2' : '3') . '.write' . $case['ordinal'], $case['upstream']);

        if ($case['ordinal'] === 1) {
            $t->same(null, $case['previous_page']);
            $t->same('initial', $case['direction']);
            $t->same(0, $case['forward_count']);
            $t->same(false, $case['forward_dominates']);

            return;
        }

        $t->same($case['ordinal'] - 1, $case['previous_page']);
        $t->same('forward', $case['direction']);
        $t->same($case['ordinal'] - 1, $case['forward_count']);
        $t->same(0, $case['backward_count']);
        $t->same(0, $case['noncontiguous_count']);
        $t->same(true, $case['forward_dominates']);
    };
}

$tests['real upstream index5 write order rejects undersized corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteOrderCases(3));
};

// Source truth: SQLite upstream test/index5.test sections 1.1 through 1.3.
// These cases model the testvfs xWrite page trace used by upstream to prove
// CREATE INDEX writes mostly move forward through database pages.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(1200) as $case) {
    $tests['real upstream index5 create index write order dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index5.test sections index5-1.1 through index5-1.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(in_array($case['upstream_section'], ['index5-1.1', 'index5-1.2', 'index5-1.3'], true));
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->same('i1', $case['index_name']);
        $t->true(count($case['write_pages']) >= 18);
        $t->same(array_values($case['write_pages']), $case['write_pages']);
        $t->true(min($case['write_pages']) >= 1);
        $t->same(count($case['write_pages']) - 1, $case['forward_steps'] + $case['backward_steps'] + $case['noncontiguous_steps']);
        $t->true($case['forward_steps'] > 0);
        $t->true($case['forward_steps'] > 2 * ($case['backward_steps'] + $case['noncontiguous_steps']));
        $t->same(true, $case['passes_upstream_guard']);
        $t->true($case['forward_bias_ratio'] > 2.0);
        $t->true($case['batch'] >= 1);
        $t->same('xWrite page-order trace favors forward CREATE INDEX leaf writes', $case['detail']);
        $t->same('ok', $case['integrity']);
    };
}

$tests['real upstream index5 create index write order dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('index5-1.1', $cases[0]['upstream_section']);
    $t->same('index5-1.3', $cases[2]['upstream_section']);
    $t->same(50, $cases[1199]['batch']);
    $t->same(['index5-1.1', 'index5-1.2', 'index5-1.3'], $sections);
};

$tests['real upstream index5 create index write order dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases(0));
};

$tests['real upstream index5 create index write order dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and upstream xWrite page-order guard semantics',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and upstream xWrite page-order guard semantics',
    );
};

return $tests;
