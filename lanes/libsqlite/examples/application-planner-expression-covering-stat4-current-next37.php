<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name_stat4_cover',
        'rootPage' => 141,
        'estimatedRows' => 60,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
            ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
            ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
            ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
            ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_stat4_cover ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_wp_options_lower_name_plain',
        'rootPage' => 142,
        'estimatedRows' => 1000,
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_plain ON wp_options(lower(option_name))',
    ],
];

$predicate = [
    'operator' => 'IN',
    'left' => ['function' => 'lower', 'column' => 'option_name'],
    'values' => ['siteurl', 'home', 'transient_feed'],
];

$ranked = SQLiteSelectExpressionIndexPlan::rankedPlans(
    $indexes,
    $predicate,
    [],
    ['autoload', 'option_value']
);
$selected = $ranked[0] ?? null;

$output = [
    'scenario' => 'application-planner-expression-covering-stat4-current-next37',
    'selectedIndex' => $selected['name'] ?? null,
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'stat4Used' => (bool) ($selected['stat4Used'] ?? false),
    'covering' => (bool) ($selected['covering'] ?? false),
    'currentNextFirst' => $selected['stat4CurrentNext'][0] ?? null,
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
            'covering' => (bool) ($plan['covering'] ?? false),
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options expression-covering planner choices with sqlite_stat4-style current/next samples, so native imports can rank skewed option_name lookups without ext/sqlite.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_lower_name_stat4_cover') {
        fwrite(STDERR, "expected stat4 covering index\n");
        exit(1);
    }
    if (($output['estimatedRows'] ?? null) !== 27 || $output['stat4Used'] !== true || $output['covering'] !== true) {
        fwrite(STDERR, "expected stat4 estimate 27 with covering plan\n");
        exit(1);
    }

    echo "application-planner-expression-covering-stat4-current-next37 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
