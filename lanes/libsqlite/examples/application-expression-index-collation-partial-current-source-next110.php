<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column, string $collation): array => [
    'function' => $function,
    'column' => $column,
    'collation' => $collation,
];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$columnPoint = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lowerName = $expr('lower', 'option_name', 'NOCASE');
$indexes = [[
    'name' => 'wp_options_lower_name_nocase_partial110',
    'rootPage' => 1101,
    'estimatedRows' => 120,
    'coveringColumns' => ['autoload', 'option_id', 'option_value'],
    'coveringExpressions' => [$lowerName],
    'stat4Samples' => [
        ['neq' => '4 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Active_Plugins', 'yes']],
        ['neq' => '7 3', 'nlt' => '4 2', 'ndlt' => '1 1', 'sample' => ['PLUGIN_ALPHA', 'yes']],
        ['neq' => '5 2', 'nlt' => '11 5', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
        ['neq' => '6 2', 'nlt' => '16 7', 'ndlt' => '3 3', 'sample' => ['Plugin_Gamma', 'yes']],
        ['neq' => '9 4', 'nlt' => '22 9', 'ndlt' => '4 4', 'sample' => ['Theme_Mods_TwentySix', 'yes']],
    ],
    'sql' => "CREATE INDEX wp_options_lower_name_nocase_partial110 ON wp_options(lower(option_name) COLLATE NOCASE, autoload, option_id) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
]];

$pointPlan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes,
    $and($columnPoint('autoload', 'yes'), $point($lowerName, 'plugin_alpha')),
    [$lowerName, ['column' => 'autoload']],
    ['autoload', 'option_id'],
    [$lowerName]
);
$rangePlan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    $indexes,
    $and($columnPoint('autoload', 'yes'), $range($lowerName, '>=', 'PLUGIN_'), $range($lowerName, '<', 'theme_')),
    [$lowerName],
    ['autoload', 'option_id']
);

echo json_encode([
    'scenario' => 'application-expression-index-collation-partial-current-source-next110',
    'point' => [
        'index' => $pointPlan['name'] ?? null,
        'queryCollation' => $pointPlan['queryCollation'] ?? null,
        'indexCollation' => $pointPlan['collation'] ?? null,
        'partial' => $pointPlan['partial'] ?? null,
        'estimatedRows' => $pointPlan['estimatedRows'] ?? null,
        'firstMatchedKey' => $pointPlan['stat4MatchedCurrentNext'][0]['current']['key'] ?? null,
        'orderBySatisfied' => $pointPlan['orderBySatisfied'] ?? null,
        'coveringExpressions' => $pointPlan['coveringExpressions'] ?? [],
    ],
    'range' => [
        'operator' => $rangePlan['operator'] ?? null,
        'estimatedRows' => $rangePlan['estimatedRows'] ?? null,
        'lowerBoundaryCurrent' => $rangePlan['stat4RangeCurrentNext']['lower']['current']['key'] ?? null,
        'upperBoundaryNext' => $rangePlan['stat4RangeCurrentNext']['upper']['next']['key'] ?? null,
        'orderBySatisfied' => $rangePlan['orderBySatisfied'] ?? null,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
