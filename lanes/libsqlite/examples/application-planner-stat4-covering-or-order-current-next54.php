<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteStat4CoveringOrOrderPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$indexes = [
    [
        'name' => 'idx_wp_options_autoload_blog_name_value_stat4',
        'rootPage' => 541,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 2],
        'stat4Samples' => [
            ['prefix' => 'no', 'suffix' => '_site_transient_update_plugins', 'nEq' => 5, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'no', 'suffix' => '_transient_doing_cron', 'nEq' => 7, 'nLt' => 5, 'nDLt' => 1],
            ['prefix' => 'no', 'suffix' => 'rewrite_rules', 'nEq' => 3, 'nLt' => 12, 'nDLt' => 2],
            ['prefix' => 'yes', 'suffix' => 'active_plugins', 'nEq' => 2, 'nLt' => 15, 'nDLt' => 3],
            ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 9, 'nLt' => 17, 'nDLt' => 4],
            ['prefix' => 'yes', 'suffix' => 'plugin_beta', 'nEq' => 4, 'nLt' => 26, 'nDLt' => 5],
            ['prefix' => 'yes', 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 30, 'nDLt' => 6],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_autoload_blog_name_value_stat4 ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
];

$predicate = $or(
    $and($point('blog_id', 1), $range('option_name', '>=', 'plugin_')),
    $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_feed')),
);
$plan = SQLiteStat4CoveringOrOrderPlan::choose(
    $indexes,
    $predicate,
    [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']],
    ['blog_id', 'option_name', 'option_value'],
);

if ($plan === null) {
    fwrite(STDERR, "planner did not produce a STAT4 covering OR plan\n");
    exit(1);
}

$summary = [
    'scenario' => 'application-planner-stat4-covering-or-order-current-next54',
    'strategy' => $plan['strategy'],
    'armCount' => $plan['armCount'],
    'indexNames' => $plan['indexNames'],
    'estimatedRows' => $plan['estimatedRows'],
    'orderBySatisfied' => $plan['orderBySatisfied'],
    'mergeOrderRequired' => $plan['mergeOrderRequired'],
    'tempSortRequired' => $plan['tempSortRequired'],
    'currentNextCount' => $plan['stat4CurrentNextCount'],
    'firstArmCurrentSuffixes' => array_column($plan['arms'][0]['stat4LoopEstimates'], 'currentSuffix'),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['strategy'] !== 'stat4-covering-single-index-or' || $summary['armCount'] !== 2 || $summary['orderBySatisfied'] !== true) {
        fwrite(STDERR, "unexpected STAT4 covering OR plan\n");
        exit(1);
    }
    fwrite(STDOUT, "application-planner-stat4-covering-or-order-current-next54 self-test passed\n");
    exit(0);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
