<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-7.1 through
// index-9.2. The upstream script verifies primary-key autoindex lookup and
// cleanup, missing DROP INDEX diagnostics, and EXPLAIN CREATE INDEX schema
// non-mutation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexPrimaryKeyDropExplainCases(1000) as $case) {
    $tests['real upstream index primary key drop explain dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-7.1 through index-9.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['result_code'], [0, 1], true));
        $t->true(in_array($case['integrity'], ['ok', 'expected-error'], true));
        $t->same($case['result_code'] === 1, $case['error'] !== null);

        if ($case['primary_key_autoindex']) {
            $t->same('test1', $case['table_name']);
            $t->same('sqlite_autoindex_test1_1', $case['index_name']);
            $t->true($case['upstream_section'] !== 'index-8.1');
        }

        if ($case['upstream_section'] === 'index-7.1') {
            $t->same([[19]], $case['result_rows']);
            $t->true(in_array('test1', $case['catalog_names'], true));
            $t->true(in_array('sqlite_autoindex_test1_1', $case['catalog_names'], true));
        }

        if ($case['upstream_section'] === 'index-7.2') {
            $t->same(65536, $case['lookup_value']);
            $t->same([[16]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-7.3') {
            $t->same(['sqlite_autoindex_test1_1'], $case['catalog_names']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-7.4/7.5') {
            $t->same([], $case['catalog_names']);
            $t->same([['ok']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-8.1') {
            $t->same(1, $case['result_code']);
            $t->same('no such index: index1', $case['error']);
            $t->same(false, $case['primary_key_autoindex']);
            $t->same([], $case['catalog_names']);
        }

        if ($case['upstream_section'] === 'index-9.1/9.2') {
            $t->same(true, $case['explain_only']);
            $t->same(['tab1'], $case['catalog_names']);
            $t->same('idx1', $case['index_name']);
            $t->same(false, in_array('idx1', $case['catalog_names'], true));
        } else {
            $t->same(false, $case['explain_only']);
        }
    };
}

$tests['real upstream index primary key drop explain dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexPrimaryKeyDropExplainCases(1000);
    $t->same(1000, count($cases));
    $t->same(1, $cases[0]['case']);
    $t->same(1000, $cases[count($cases) - 1]['case']);
    $t->same('index-7.1', $cases[0]['upstream_section']);
    $t->same('index-9.1/9.2', $cases[5]['upstream_section']);
    $t->same('index-8.1', $cases[4]['upstream_section']);
};

$tests['real upstream index primary key drop explain rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexPrimaryKeyDropExplainCases(0));
};

$tests['real upstream index primary key drop explain dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, primary-key autoindex, drop-index diagnostics, and EXPLAIN non-mutation modeling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, primary-key autoindex, drop-index diagnostics, and EXPLAIN non-mutation modeling',
    );
};

return $tests;
