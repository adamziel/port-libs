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

return $tests;
