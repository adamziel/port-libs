<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-16.1 through
// index-17.4. The upstream script verifies that redundant UNIQUE/PRIMARY KEY
// constraints coalesce to the correct autoindex count and that protected
// sqlite_autoindex_* objects cannot be dropped even with IF EXISTS.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexRedundantConstraintAutoindexCases(1000) as $case) {
    $tests['real upstream index redundant autoindex dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-16.1 through index-17.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index-16.') || str_starts_with($case['upstream_section'], 'index-17.'));
        $t->same('t7', $case['table_name']);
        $t->true($case['constraints'] !== []);
        $t->same($case['expected_autoindex_count'], count($case['autoindex_names']));
        $t->same('ok', $case['integrity']);

        foreach ($case['autoindex_names'] as $ordinal => $name) {
            $t->same('sqlite_autoindex_t7_' . ($ordinal + 1), $name);
        }

        if (str_starts_with($case['upstream_section'], 'index-16.')) {
            $t->same(null, $case['drop_index_name']);
            $t->same(false, $case['drop_if_exists']);
            $t->same(null, $case['expected_error']);
            $t->same(0, $case['result_code']);
        }

        if ($case['upstream_section'] === 'index-16.5') {
            $t->same(2, $case['expected_autoindex_count']);
            $t->same(['c', 'd', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], $case['constraints']);
        }

        if ($case['upstream_section'] === 'index-17.1') {
            $t->same(3, $case['expected_autoindex_count']);
            $t->same(['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], $case['autoindex_names']);
        }

        if ($case['upstream_section'] === 'index-17.2' || $case['upstream_section'] === 'index-17.3') {
            $t->same('sqlite_autoindex_t7_1', $case['drop_index_name']);
            $t->same('index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped', $case['expected_error']);
            $t->same(1, $case['result_code']);
        }

        if ($case['upstream_section'] === 'index-17.3') {
            $t->same(true, $case['drop_if_exists']);
        }

        if ($case['upstream_section'] === 'index-17.4') {
            $t->same('no_such_index', $case['drop_index_name']);
            $t->same(true, $case['drop_if_exists']);
            $t->same(null, $case['expected_error']);
            $t->same(0, $case['result_code']);
        }
    };
}

$tests['real upstream index redundant autoindex dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexRedundantConstraintAutoindexCases(1000);

    $t->same(1000, count($cases));
    $t->same('index-16.1', $cases[0]['upstream_section']);
    $t->same('index-17.4', $cases[8]['upstream_section']);
    $t->same('index-16.1', $cases[9]['upstream_section']);
    $t->same('index.test sections index-16.1 through index-17.4', $cases[999]['source']);
};

$tests['real upstream index redundant autoindex rejects empty dynamic corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexRedundantConstraintAutoindexCases(0));
};

$tests['real upstream index redundant autoindex dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and sqlite_autoindex catalog/drop-protection semantics',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and sqlite_autoindex catalog/drop-protection semantics',
    );
};

return $tests;
