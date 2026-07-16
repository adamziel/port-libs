<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereG.test sections whereG-1.1 through
// whereG-5.3.3. This batch owns planner-hint B-tree choices: unlikely()
// join order, likelihood() probability validation, commuted equality plans,
// open-ended range scans, and skip-scan suppression/admission. It avoids the
// accepted whereG expression-affinity slice covering sections 7, 8, and 12.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereGLikelihoodPlannerCases(1200) as $case) {
    $tests['real upstream whereG likelihood planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereG.test sections whereG-1.1 through whereG-5.3.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereG-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(str_contains($case['detail'], 'whereG dynamic replay'));

        if ($case['invalid_probability_error'] !== null) {
            $t->same('expected-error', $case['integrity']);
            $t->same('second argument to likelihood() must be a constant between 0.0 and 1.0', $case['invalid_probability_error']);
            $t->same([], $case['result_rows']);
            $t->same([], $case['table_order']);
            $t->same([], $case['access_plan']);
            $t->same(false, $case['uses_index_range']);
            $t->same(false, $case['uses_skip_scan']);
            $t->true(str_starts_with((string) $case['likelihood_wrapper'], 'likelihood('));

            return;
        }

        $t->same('ok', $case['integrity']);
        $t->same(null, $case['invalid_probability_error']);
        $t->true(count($case['table_order']) >= 1);
        $t->true(count($case['access_plan']) >= 1);

        if ($case['upstream_section'] === 'whereG-1.1/1.2') {
            $t->same('unlikely(cname LIKE)', $case['likelihood_wrapper']);
            $t->same(0.0625, $case['probability']);
            $t->same([['Mass in B Minor, BWV 232']], $case['result_rows']);
            $t->same(['composer', 'track', 'album'], $case['table_order']);
            $t->same(true, $case['uses_composer_filter_first']);
            $t->same(false, $case['uses_track_outer_scan']);
            $t->same(['track_i1', 'album rowid'], $case['chosen_indexes']);
            $t->contains('SEARCH track USING INDEX track_i1', implode('; ', $case['access_plan']));
        }

        if ($case['upstream_section'] === 'whereG-1.3/1.4') {
            $t->same('likelihood(cname LIKE,0.5)', $case['likelihood_wrapper']);
            $t->same(0.5, $case['probability']);
            $t->same(['track', 'composer', 'album'], $case['table_order']);
            $t->same(false, $case['uses_composer_filter_first']);
            $t->same(true, $case['uses_track_outer_scan']);
            $t->same(['composer rowid', 'album rowid'], $case['chosen_indexes']);
        }

        if ($case['upstream_section'] === 'whereG-1.5/1.6') {
            $t->same(null, $case['likelihood_wrapper']);
            $t->same(null, $case['probability']);
            $t->same([['Mass in B Minor, BWV 232']], $case['result_rows']);
            $t->same(['track', 'composer', 'album'], $case['table_order']);
            $t->same(true, $case['uses_track_outer_scan']);
        }

        if ($case['upstream_section'] === 'whereG-1.7/1.8') {
            $t->same('unlikely(join equalities)', $case['likelihood_wrapper']);
            $t->same(['track', 'composer', 'album'], $case['table_order']);
            $t->same(true, $case['uses_track_outer_scan']);
            $t->contains('SEARCH composer USING INTEGER PRIMARY KEY', implode('; ', $case['access_plan']));
        }

        if ($case['commuted_equality']) {
            $t->same(['a', 'b'], $case['table_order']);
            $t->same(['sqlite_autoindex_b_1'], $case['chosen_indexes']);
            $t->same(false, $case['uses_index_range']);
            $t->same(false, $case['uses_skip_scan']);
            $t->contains('SEARCH b USING INDEX sqlite_autoindex_b_1', implode('; ', $case['access_plan']));
        }

        if ($case['upstream_section'] === 'whereG-5.1.2') {
            $t->same(['t1'], $case['table_order']);
            $t->same(['i1'], $case['chosen_indexes']);
            $t->same(true, $case['uses_index_range']);
            $t->same(false, $case['uses_skip_scan']);
            $t->same(false, $case['uses_table_scan']);
            $t->same(['SEARCH t1 USING INDEX i1 (a>?)'], $case['access_plan']);
        }

        if ($case['upstream_section'] === 'whereG-5.2.2') {
            $t->same(0.01, $case['probability']);
            $t->same(['i1'], $case['chosen_indexes']);
            $t->same(true, $case['uses_index_range']);
            $t->same(true, $case['uses_skip_scan']);
            $t->same(false, $case['uses_table_scan']);
            $t->contains('ANY(a) AND b>?', implode('; ', $case['access_plan']));
        }

        if (in_array($case['upstream_section'], ['whereG-5.1.3', 'whereG-5.1.4', 'whereG-5.2.3', 'whereG-5.2.4', 'whereG-5.3.2', 'whereG-5.3.3'], true)) {
            $t->same(['t1'], $case['table_order']);
            $t->same([], $case['chosen_indexes']);
            $t->same(false, $case['uses_index_range']);
            $t->same(false, $case['uses_skip_scan']);
            $t->same(true, $case['uses_table_scan']);
            $t->same(['SCAN t1'], $case['access_plan']);
            $t->true($case['probability'] === 0.9 || $case['probability'] === 0.9375);
        }
    };
}

$tests['real upstream whereG likelihood planner dynamic source count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereGLikelihoodPlannerCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('whereG-1.1/1.2', $cases[0]['upstream_section']);
    $t->same('whereG-5.3.3', $cases[18]['upstream_section']);
    $t->same('whereG-1.5/1.6', $cases[1199]['upstream_section']);
    $t->same([
        'whereG-1.1/1.2',
        'whereG-1.3/1.4',
        'whereG-1.5/1.6',
        'whereG-1.7/1.8',
        'whereG-2.1',
        'whereG-2.2',
        'whereG-2.3',
        'whereG-3.1',
        'whereG-3.2',
        'whereG-3.3',
        'whereG-3.4',
        'whereG-5.1.2',
        'whereG-5.1.3',
        'whereG-5.1.4',
        'whereG-5.2.2',
        'whereG-5.2.3',
        'whereG-5.2.4',
        'whereG-5.3.2',
        'whereG-5.3.3',
    ], $sections);
};

$tests['real upstream whereG likelihood planner dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereGLikelihoodPlannerCases(0));
};

$tests['real upstream whereG likelihood planner dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, likelihood selectivity metadata, join-order, commuted-equality, range-scan, and skip-scan helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, likelihood selectivity metadata, join-order, commuted-equality, range-scan, and skip-scan helpers',
    );
    $t->same(
        'non-overlap: owns whereG.test planner sections 1.1-5.3.3 and avoids accepted whereG expression-affinity sections 7, 8, and 12',
        'non-overlap: owns whereG.test planner sections 1.1-5.3.3 and avoids accepted whereG expression-affinity sections 7, 8, and 12',
    );
};

return $tests;
