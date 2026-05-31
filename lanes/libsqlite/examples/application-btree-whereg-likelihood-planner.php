<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cases = SQLiteBTreeIndexDynamicCorpusPlan::whereGLikelihoodPlannerCases(19);
$bySection = [];
foreach ($cases as $case) {
    $bySection[$case['upstream_section']] = $case;
}

$summary = [
    'scenario' => 'application-btree-whereg-likelihood-planner',
    'upstream' => 'SQLite test/whereG.test sections whereG-1.1 through whereG-5.3.3',
    'caseCount' => count($cases),
    'rareComposerJoinOrder' => $bySection['whereG-1.1/1.2']['table_order'],
    'neutralJoinOrder' => $bySection['whereG-1.3/1.4']['table_order'],
    'rangePlan' => $bySection['whereG-5.1.2']['access_plan'],
    'rareSkipScanPlan' => $bySection['whereG-5.2.2']['access_plan'],
    'commonRangePlan' => $bySection['whereG-5.2.3']['access_plan'],
    'invalidProbabilityError' => $bySection['whereG-2.1']['invalid_probability_error'],
    'applicationUse' => 'Preview how planner likelihood hints affect generic catalog/search joins and B-tree index scans before executing the query without ext/sqlite.',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['caseCount'] === 19);
    assert($summary['rareComposerJoinOrder'] === ['composer', 'track', 'album']);
    assert($summary['neutralJoinOrder'] === ['track', 'composer', 'album']);
    assert($summary['rangePlan'] === ['SEARCH t1 USING INDEX i1 (a>?)']);
    assert($summary['rareSkipScanPlan'] === ['SEARCH t1 USING INDEX i1 (ANY(a) AND b>?)']);
    assert($summary['commonRangePlan'] === ['SCAN t1']);
    assert($summary['invalidProbabilityError'] === 'second argument to likelihood() must be a constant between 0.0 and 1.0');
    fwrite(STDOUT, "application-btree-whereg-likelihood-planner self-test passed\n");

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
