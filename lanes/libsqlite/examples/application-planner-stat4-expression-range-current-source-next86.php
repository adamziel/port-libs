<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name_stat4_current_source',
        'rootPage' => 286,
        'estimatedRows' => 60,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
            ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
            ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
            ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
            ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_stat4_current_source ON wp_options(lower(option_name), autoload, option_value)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        $range($expr('lower', 'option_name'), '>=', 'active_plugins'),
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'widget_recent'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout'),
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    $indexes,
    $predicate,
    [],
    ['autoload', 'option_value']
);

$output = [
    'scenario' => 'application-planner-stat4-expression-range-current-source-next86',
    'selectedIndex' => $plan['name'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'effectiveRange' => $plan['values'] ?? null,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
    'rangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied wp_options redundant lower()/range predicates using the current effective STAT4 expression-index interval instead of looser source predicates before choosing a native PHP import/query plan.',
];

if (($argv[1] ?? '') === '--self-test') {
    $rangeEvidence = $output['rangeCurrentNext'] ?? null;
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_lower_name_stat4_current_source') {
        fwrite(STDERR, "expected STAT4 current-source expression range index\n");
        exit(1);
    }
    if (($output['effectiveRange']['lower'] ?? null) !== 'home' || ($output['effectiveRange']['upper'] ?? null) !== 'transient_timeout') {
        fwrite(STDERR, "expected tight current-source expression range\n");
        exit(1);
    }
    if (!is_array($rangeEvidence) || ($rangeEvidence['lower']['current']['key'] ?? null) !== 'home' || ($rangeEvidence['upper']['current']['key'] ?? null) !== 'transient_feed') {
        fwrite(STDERR, "expected tight STAT4 current/next boundaries\n");
        exit(1);
    }

    echo "application-planner-stat4-expression-range-current-source-next86 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
