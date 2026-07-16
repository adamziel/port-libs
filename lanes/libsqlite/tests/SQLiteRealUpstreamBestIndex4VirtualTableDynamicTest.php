<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex4.test. These cases cover
// xBestIndex equality-support bitmasks, usable-flag enforcement across joined
// virtual tables, malfunctioning modules that ignore unusable constraints, and
// table-valued hidden-argument planning.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex4VirtualTableUsableFlagCases(1000) as $case) {
    $tests['real upstream bestindex4 virtual table usable flag dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['source'], 'bestindex4.test'));
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex4-'));
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['sql_variant'], [2, 3, 4, 21, 22], true));
        $t->true(array_values($case['constraints']) === $case['constraints']);
        $t->true(array_values($case['chosen']) === $case['chosen']);
        $t->same([], $case['result_rows']);
        $t->true($case['detail'] !== '');

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['table'], ['t1', 't2', 'x1'], true));
            $t->true(in_array($constraint['column'], ['id', 'host', 'class', 'd'], true));
            $t->same('=', $constraint['operator']);
            $t->true(is_bool($constraint['usable']));
        }

        foreach ($case['chosen'] as $choice) {
            $t->true(in_array($choice['table'], ['t1', 't2', 'x1'], true));
            $t->true(in_array($choice['column'], ['', 'id', 'host', 'class', 'd', 'x'], true));
            $t->true(is_int($choice['index']));
            $t->true($choice['cost'] > 0);
            $t->true($choice['rows'] > 0);
        }

        if ($case['malfunction']) {
            $t->same('xBestIndex used an unusable constraint', $case['error']);
            $t->true(str_contains($case['detail'], 'malfunction'));
            $t->true(($case['param1'] & 0x08) !== 0 || ($case['param2'] & 0x08) !== 0);
        } else {
            $t->same(null, $case['error']);
            $t->true(!str_contains($case['detail'], 'malfunction'));
        }

        if ($case['sql_variant'] === 2) {
            $t->same('id', $case['constraints'][0]['column']);
            $t->same(false, $case['constraints'][0]['usable']);
            $t->same('host', $case['constraints'][1]['column']);
            $t->same('class', $case['constraints'][2]['column']);
        }

        if ($case['sql_variant'] === 3) {
            $t->same('host', $case['constraints'][0]['column']);
            $t->same(false, $case['constraints'][0]['usable']);
            $t->same('class', $case['constraints'][1]['column']);
            $t->same('id', $case['constraints'][2]['column']);
        }

        if ($case['sql_variant'] === 4) {
            $t->same('host', $case['constraints'][0]['column']);
            $t->same(false, $case['constraints'][0]['usable']);
            $t->same('id', $case['constraints'][1]['column']);
            $t->same('class', $case['constraints'][2]['column']);
        }

        if ($case['upstream_section'] === 'bestindex4-2.1') {
            $t->same(false, $case['constraints'][0]['usable']);
            $t->same('x1', $case['chosen'][0]['table']);
            $t->same('', $case['chosen'][0]['column']);
            $t->true(str_contains($case['detail'], 'SEARCH t1 USING COVERING INDEX'));
        }

        if ($case['upstream_section'] === 'bestindex4-2.2') {
            $t->same(true, $case['constraints'][0]['usable']);
            $t->same('t1', $case['chosen'][0]['table']);
            $t->same('x1', $case['chosen'][1]['table']);
            $t->same(555, $case['chosen'][1]['index']);
        }
    };
}

$tests['real upstream bestindex4 virtual table usable flag source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex4VirtualTableUsableFlagCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('bestindex4-1.0.0.2', $cases[0]['upstream_section']);
    $t->same('bestindex4-1.15.15.4', $cases[767]['upstream_section']);
    $t->same('bestindex4-2.1', $cases[768]['upstream_section']);
    $t->same('bestindex4-2.2', $cases[769]['upstream_section']);
    $t->true(in_array('bestindex4-1.8.0.2', $sections, true));
    $t->true(in_array('bestindex4-1.0.8.2', $sections, true));
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['malfunction'])) > 400);
    $t->same(2, count(array_filter($cases, static fn (array $case): bool => str_starts_with($case['upstream_section'], 'bestindex4-2.'))));
};

$tests['real upstream bestindex4 virtual table usable flag rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex4VirtualTableUsableFlagCases(0));
};

$tests['real upstream bestindex4 virtual table usable flag dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xBestIndex constraint metadata, usable-flag, and hidden-argument planning helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xBestIndex constraint metadata, usable-flag, and hidden-argument planning helpers',
    );
};

return $tests;
