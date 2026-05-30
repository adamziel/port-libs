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

$lower = ['function' => 'lower', 'column' => 'option_name'];
$autoload = static fn (): array => ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'];
$arm = static fn (string $name): array => [
    'operator' => 'AND',
    'terms' => [
        $autoload(),
        ['operator' => '=', 'left' => $lower, 'right' => $name],
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan(
    [[
        'name' => 'idx_wp_plugin_autoload_stat4',
        'rootPage' => 531,
        'estimatedRows' => 90,
        'coveringColumns' => ['autoload', 'option_id', 'option_value'],
        'stat4Samples' => [
            ['neq' => '7 7 2', 'nlt' => '2 2 1', 'ndlt' => '1 1 1', 'sample' => ['plugin_alpha', 'yes', 19]],
            ['neq' => '5 5 3', 'nlt' => '9 9 3', 'ndlt' => '2 2 2', 'sample' => ['plugin_beta', 'yes', 17]],
            ['neq' => '13 13 4', 'nlt' => '14 14 6', 'ndlt' => '3 3 3', 'sample' => ['plugin_gamma', 'yes', 13]],
        ],
        'sql' => "CREATE INDEX idx_wp_plugin_autoload_stat4 ON wp_options(lower(option_name), autoload, option_id DESC, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ]],
    ['operator' => 'OR', 'terms' => [$arm('plugin_alpha'), $arm('plugin_beta'), $arm('plugin_alpha')]],
    [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']],
    ['autoload', 'option_id'],
);

echo json_encode([
    'strategy' => $plan['strategy'],
    'estimatedRows' => $plan['estimatedRows'],
    'values' => $plan['inRewrite']['values'],
    'stat4Used' => $plan['stat4Used'],
], JSON_PRETTY_PRINT) . PHP_EOL;
