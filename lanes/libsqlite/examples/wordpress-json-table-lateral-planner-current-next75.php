<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentOptions = [
    [
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}],"version":3}',
        'scan_root' => '$.rules',
    ],
    [
        'option_name' => 'plugin_orphan_settings',
        'option_value' => null,
        'scan_root' => '$.rules',
    ],
];

$nextOptions = [
    [
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":3},{"name":"cache","priority":6},{"name":"shop","priority":5}],"version":4}',
        'scan_root' => '$.rules',
    ],
    [
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":[{"name":"gallery","priority":5}],"version":1}',
        'scan_root' => '$.rules',
    ],
];

$plan = SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    $currentOptions,
    $nextOptions,
    'option_value',
    'json_tree',
    [
        ['column' => 'key', 'operator' => 'IN', 'value' => ['name', 'priority']],
        ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
        ['column' => 'limit', 'operator' => '=', 'value' => 8],
    ],
    'scan_root',
    [['column' => 'id']],
);

$summary = [
    'scenario' => 'wordpress-json-table-lateral-planner-current-next75',
    'replanRequired' => $plan['replanRequired'],
    'replanReasons' => $plan['replanReasons'],
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'transitionReasons' => array_column($plan['transitions'], 'reason'),
    'currentArgumentCounts' => array_map(static fn (array $row): int => count($row['filterArguments']), $plan['current']),
    'nextArgumentCounts' => array_map(static fn (array $row): int => count($row['filterArguments']), $plan['next']),
    'dependency' => $plan['dependencies'][0],
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;

if (
    $summary['replanRequired'] !== true
    || $summary['transitionReasons'] !== ['lateral-filter-argument-tape-changed', 'next-lateral-plan-becomes-runnable']
    || $summary['dependency'] !== 'sqlite-json-table-lateral-constraint-planner-comparison'
) {
    fwrite(STDERR, "wordpress-json-table-lateral-planner-current-next75 self-test failed\n");
    exit(1);
}
