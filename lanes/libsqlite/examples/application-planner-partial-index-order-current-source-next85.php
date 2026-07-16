<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePartialIndexOrderCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = [
    [
        'name' => 'idx_wp_plugin_options_by_blog_name',
        'rootPage' => 8501,
        'estimatedRows' => 2400,
        'sql' => "CREATE INDEX idx_wp_plugin_options_by_blog_name ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_wp_options_by_blog_name',
        'rootPage' => 8502,
        'estimatedRows' => 1600,
        'sql' => 'CREATE INDEX idx_wp_options_by_blog_name ON wp_options(blog_id, option_name, autoload)',
    ],
];

$plan = SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes,
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_'),
        $range('autoload', '<', 'z'),
    ),
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'usable');
    assert($plan['name'] === 'idx_wp_plugin_options_by_blog_name');
    assert($plan['partialIndexOrderUsable'] === true);
    assert($plan['orderByMode'] === 'partial-current-source');
    assert($plan['blockSortRequired'] === false);
    assert($plan['nextSource'] === 'covering-index');
    echo "application-planner-partial-index-order-current-source-next85 self-test passed\n";

    return;
}

echo json_encode([
    'scenario' => 'application-planner-partial-index-order-current-source-next85',
    'sqlShape' => "SELECT option_name, autoload, option_value FROM wp_options WHERE kind = 'plugin' AND blog_id = 1 AND option_name >= 'plugin_' AND autoload < 'z' ORDER BY option_name",
    'selectedIndex' => $plan['name'],
    'partialIndexOrderUsable' => $plan['partialIndexOrderUsable'],
    'orderByMode' => $plan['orderByMode'],
    'currentSource' => $plan['currentSource'],
    'nextSource' => $plan['nextSource'],
    'residualPredicateRequired' => $plan['nextResidualPredicateRequired'],
    'detail' => $plan['detail'],
    'applicationUse' => 'Copied wp_options plugin diagnostics can stream ORDER BY option_name from an implied partial index current source, while next-source residual predicates remain explicit and no temp sort is introduced without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
