<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$predicate = $and(
    $point('autoload', 'yes'),
    $range($expr('lower', 'option_name'), '>=', 'plugin_'),
    $range($expr('lower', 'option_name'), '<', 'theme_')
);

$plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
    [[
        'name' => 'wp_options_lower_name_autoload',
        'rootPage' => 7401,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'stat4Samples' => [
            ['neq' => '8 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '9 3', 'nlt' => '8 2', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '7 2', 'nlt' => '17 5', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
            ['neq' => '6 2', 'nlt' => '24 7', 'ndlt' => '3 3', 'sample' => ['plugin_zeta', 'no']],
            ['neq' => '11 4', 'nlt' => '30 9', 'ndlt' => '4 4', 'sample' => ['theme_mods_twentysix', 'yes']],
        ],
        'sql' => "CREATE INDEX wp_options_lower_name_autoload ON wp_options(lower(option_name), autoload, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ]],
    $predicate,
    [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']],
    ['autoload', 'option_value']
);

echo json_encode([
    'index' => $plan['name'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'bounds' => $plan['values'] ?? null,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'estimatedCost' => $plan['estimatedCost'] ?? null,
    'matchedCurrentKeys' => array_column(array_column($plan['stat4MatchedCurrentNext'] ?? [], 'current'), 'key'),
    'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
    'covering' => $plan['covering'] ?? false,
    'legacyPlansUnaffected' => $plan['legacyPlansUnaffected'] ?? false,
], JSON_PRETTY_PRINT) . PHP_EOL;
