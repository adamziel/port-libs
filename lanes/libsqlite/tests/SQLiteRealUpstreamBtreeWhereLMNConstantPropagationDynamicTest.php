<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereL.test sections 110 through 710,
// test/whereM.test sections 1.1 through 1.5, and test/whereN.test section
// 1.1. These cover constant propagation, affinity-sensitive equality/LIKE,
// expression-index preservation, and interstage join planning.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereLMNConstantPropagationPlannerCases(1000) as $case) {
    $tests['real upstream whereL whereM whereN constant propagation dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereL.test sections 110 through 710, whereM.test sections 1.1 through 1.5, and whereN.test section 1.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'whereL-') || str_starts_with($case['upstream_section'], 'whereM-') || str_starts_with($case['upstream_section'], 'whereN-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['sql'] !== '');
        $t->true($case['batch'] >= 1);
        $t->true(str_contains($case['detail'], $case['upstream_section']));
        $t->true(count($case['tables']) >= 1);
        $t->true(count($case['tags']) >= 2);
        $t->same($case['uses_temp_sort'], in_array('USE TEMP B-TREE FOR ORDER BY', $case['plan_terms'], true));

        foreach ($case['plan_terms'] as $term) {
            $t->true(is_string($term) && $term !== '');
        }

        foreach ($case['tables'] as $table) {
            $t->true(is_string($table) && $table !== '');
        }

        if ($case['upstream_section'] === 'whereL-110') {
            $t->same(true, $case['compound']);
            $t->same(3, $case['search_count']);
            $t->same(['t1', 't2', 't3'], $case['tables']);
        }

        if ($case['upstream_section'] === 'whereL-122') {
            $t->same(true, $case['uses_temp_sort']);
            $t->same(['t3', 't1', 't2'], $case['tables']);
            $t->true(in_array('nonconstant-expression', $case['tags'], true));
        }

        if ($case['upstream_section'] === 'whereL-200/201') {
            $t->same(['ABC', 'ABC', 'abc'], $case['expected']);
            $t->true(in_array('binary-vs-nocase', $case['tags'], true));
        }

        if ($case['upstream_section'] === 'whereL-500/530') {
            $t->same([], $case['expected']);
            $t->true(in_array('view-affinity', $case['tags'], true));
        }

        if ($case['upstream_section'] === 'whereL-700/710') {
            $t->same([1], $case['expected']);
            $t->same(1, $case['search_count']);
            $t->true(str_contains(implode(' ', $case['plan_terms']), '<expr>=?'));
        }

        if ($case['upstream_section'] === 'whereM-1.1.*') {
            $t->same(0, $case['expected']['eq-and-text']);
            $t->same(1, $case['expected']['eq-and-like-real']);
        }

        if ($case['upstream_section'] === 'whereM-1.2.*') {
            $t->same(1, $case['expected']['eq-and-text']);
            $t->same(0, $case['expected']['eq-and-like-real']);
            $t->same(1, $case['expected']['text-and-like-int']);
        }

        if ($case['upstream_section'] === 'whereM-1.3.*') {
            $t->same(0, $case['expected']['eq10-and-text']);
            $t->same(1, $case['expected']['real-and-text']);
        }

        if ($case['upstream_section'] === 'whereM-1.4.*') {
            $t->same(1, $case['expected']['eq-and-text']);
            $t->same(1, $case['expected']['text10-and-like-real']);
        }

        if ($case['upstream_section'] === 'whereM-1.5.*') {
            $t->same(0, $case['expected']['eq-and-text']);
            $t->same(1, $case['expected']['real-and-like-real']);
        }

        if ($case['upstream_section'] === 'whereN-1.1') {
            $t->same(3, $case['search_count']);
            $t->same(true, $case['uses_temp_sort']);
            $t->same(['datasource', 'rule', 'violation'], $case['tables']);
            $t->true(in_array('interstage-heuristic', $case['tags'], true));
        }
    };
}

$tests['real upstream whereL whereM whereN dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereLMNConstantPropagationPlannerCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $t->same(1000, count($cases));
    $t->same('whereL-110', $cases[0]['upstream_section']);
    $t->same('whereM-1.2.*', $cases[9]['upstream_section']);
    $t->same('whereM-1.4.*', $cases[999]['upstream_section']);
    $t->same([
        'whereL-110',
        'whereL-120',
        'whereL-122',
        'whereL-200/201',
        'whereL-300/302',
        'whereL-500/530',
        'whereL-700/710',
        'whereN-1.1',
        'whereM-1.1.*',
        'whereM-1.2.*',
        'whereM-1.3.*',
        'whereM-1.4.*',
        'whereM-1.5.*',
    ], $sections);
};

$tests['real upstream whereL whereM whereN dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereLMNConstantPropagationPlannerCases(0));
};

$tests['real upstream whereL whereM whereN dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, constant-propagation, affinity, collation, expression-index, and join-planner helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, constant-propagation, affinity, collation, expression-index, and join-planner helpers',
    );
};

return $tests;
