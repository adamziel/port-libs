<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name_stat4_range',
        'rootPage' => 181,
        'estimatedRows' => 60,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
            ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
            ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
            ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
            ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_stat4_range ON wp_options(lower(option_name), autoload, option_value)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'home'],
        ['operator' => '<', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'transient_timeout'],
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    $indexes,
    $predicate,
    [],
    ['autoload', 'option_value']
);

$output = [
    'scenario' => 'application-planner-stat4-expression-range-current-next81',
    'selectedIndex' => $plan['name'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
    'covering' => (bool) ($plan['covering'] ?? false),
    'rangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied wp_options expression-index range scans with sqlite_stat4 current/next sample boundaries before choosing a native PHP import/query plan.',
];

if (($argv[1] ?? '') === '--self-test') {
    $range = $output['rangeCurrentNext'] ?? null;
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_lower_name_stat4_range') {
        fwrite(STDERR, "expected STAT4 expression range index\n");
        exit(1);
    }
    if (($output['operator'] ?? null) !== 'range-bounded' || ($output['stat4Used'] ?? null) !== true || ($output['covering'] ?? null) !== true) {
        fwrite(STDERR, "expected bounded STAT4 covering range\n");
        exit(1);
    }
    if (!is_array($range) || ($range['lower']['current']['key'] ?? null) !== 'home' || ($range['upper']['current']['key'] ?? null) !== 'transient_feed') {
        fwrite(STDERR, "expected STAT4 current/next range boundaries\n");
        exit(1);
    }

    echo "application-planner-stat4-expression-range-current-next81 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
