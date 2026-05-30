<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lowerName = $expr('lower', 'option_name');
$index = [
    'name' => 'wp_options_lower_autoload_partial_stat4_order',
    'rootPage' => 491,
    'estimatedRows' => 80,
    'stat4Samples' => [
        ['neq' => '2 2 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['active_plugins', 'yes', 11]],
        ['neq' => '7 7 2', 'nlt' => '2 2 1', 'ndlt' => '1 1 1', 'sample' => ['plugin_alpha', 'yes', 19]],
        ['neq' => '5 5 3', 'nlt' => '9 9 3', 'ndlt' => '2 2 2', 'sample' => ['plugin_beta', 'yes', 17]],
        ['neq' => '13 13 4', 'nlt' => '14 14 6', 'ndlt' => '3 3 3', 'sample' => ['plugin_gamma', 'yes', 13]],
        ['neq' => '3 3 1', 'nlt' => '27 27 10', 'ndlt' => '4 4 4', 'sample' => ['siteurl', 'yes', 5]],
        ['neq' => '21 21 8', 'nlt' => '30 30 11', 'ndlt' => '5 5 5', 'sample' => ['transient_feed', 'no', 3]],
    ],
    'sql' => "CREATE INDEX wp_options_lower_autoload_partial_stat4_order ON wp_options(lower(option_name), autoload, option_id DESC, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    [$index],
    $and($point('autoload', 'yes'), $range($lowerName, '>=', 'plugin_')),
    [
        ['function' => 'lower', 'column' => 'option_name'],
        ['column' => 'autoload'],
        ['column' => 'option_id', 'direction' => 'DESC'],
    ],
    ['autoload', 'option_id', 'option_value'],
);

echo json_encode([
    'scenario' => 'application-planner-stat4-partial-expression-order-current-next49',
    'index' => $plan['name'],
    'partial' => $plan['partial'],
    'covering' => $plan['covering'],
    'orderBySatisfied' => $plan['orderBySatisfied'],
    'estimatedRows' => $plan['estimatedRows'],
    'stat4Used' => $plan['stat4Used'],
    'currentNext' => array_slice($plan['stat4CurrentNext'], 1, 2),
    'applicationUse' => 'Preview copied wp_options plugin-option scans where autoload and lower(option_name) prove a partial expression index, STAT4 supplies current/next selectivity, and ORDER BY lower(option_name), autoload, option_id DESC is satisfied without a table sort.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
