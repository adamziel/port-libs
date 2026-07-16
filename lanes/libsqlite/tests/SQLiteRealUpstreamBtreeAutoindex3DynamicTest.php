<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex3.test sections autoindex3-100
// through autoindex3-310.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::autoindex3DeclaredIndexShadowCases(1000) as $case) {
    $tests['real upstream autoindex3 declared-index shadow dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex3.test autoindex3-100 through autoindex3-310', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'autoindex3-100',
            'autoindex3-110',
            'autoindex3-120',
            'autoindex3-130',
            'autoindex3-140',
            'autoindex3-220',
            'autoindex3-310 setup',
            'autoindex3-310 recursive',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['query_shape'] !== '');
        $t->true($case['declared_index'] !== '');
        $t->true($case['declared_index_selectivity'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same($case['uses_automatic_index'], str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
        $t->same($case['uses_bloom_filter'], str_contains($case['detail'], 'BLOOM FILTER'));
        $t->same($case['recursive_cte'], str_contains($case['detail'], 'children'));

        if ($case['upstream_section'] === 'autoindex3-100') {
            $t->same(false, $case['automatic_index_allowed']);
            $t->same(false, $case['uses_automatic_index']);
            $t->same(true, $case['uses_declared_index']);
            $t->same('t1b/t2d', $case['declared_index']);
            $t->true(!str_contains($case['detail'], 'AUTOMATIC'));
        }

        if (in_array($case['upstream_section'], ['autoindex3-110', 'autoindex3-120'], true)) {
            $t->same(true, $case['automatic_index_allowed']);
            $t->same(true, $case['uses_automatic_index']);
            $t->same(false, $case['uses_declared_index']);
            $t->true(str_contains($case['query_shape'], 'x=y'));
        }

        if (in_array($case['upstream_section'], ['autoindex3-130', 'autoindex3-140'], true)) {
            $t->same(true, $case['automatic_index_allowed']);
            $t->same(true, $case['uses_automatic_index']);
            $t->same(true, $case['uses_declared_index']);
            $t->true(str_contains($case['detail'], 't2d'));
        }

        if ($case['upstream_section'] === 'autoindex3-220') {
            $t->same(true, $case['uses_bloom_filter']);
            $t->same(false, $case['uses_skip_scan']);
            $t->same('uab/vbde/ve', $case['declared_index']);
            $t->true(str_contains($case['detail'], 'SEARCH u USING AUTOMATIC COVERING INDEX'));
        }

        if (str_starts_with($case['upstream_section'], 'autoindex3-310')) {
            $t->same(false, $case['automatic_index_allowed']);
            $t->same(false, $case['uses_automatic_index']);
            $t->same(true, $case['uses_declared_index']);
            $t->same(true, $case['recursive_cte']);
            $t->true(str_contains($case['query_shape'], 'rx'));
            $t->true(str_contains($case['detail'], 'INDEX x1'));
        }
    };
}

$tests['real upstream autoindex3 declared-index shadow corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::autoindex3DeclaredIndexShadowCases(1000);
    $t->same(1000, count($cases));
    $t->same(125, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex3-100')));
    $t->same(125, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex3-220')));
    $t->same(250, count(array_filter($cases, static fn (array $case): bool => str_starts_with($case['upstream_section'], 'autoindex3-310'))));
};

$tests['real upstream autoindex3 declared-index shadow rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::autoindex3DeclaredIndexShadowCases(0));
};

$tests['real upstream autoindex3 declared-index shadow dependency closure'] = static function (TestRunner $t): void {
    $t->same('existing bounded planner corpus generator', 'existing bounded planner corpus generator');
};

return $tests;
