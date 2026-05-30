<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows = [
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
];
$payload = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'blog_id' => $row['blog_id'],
        'autoload' => $row['autoload'],
    ],
];

$prepared = [
    'name' => 'wp-options-prepared-next254269',
    'schemaCookie' => 2540,
    'stat4Generation' => 254,
    'rows' => [$rows[3]],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_next254269',
        'rootPage' => 25401,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ],
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 1, 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];
$current = $prepared;
$current['name'] = 'wp-options-current-next254269';
$current['schemaCookie'] = 2699;
$current['stat4Generation'] = 1269;
$current['indexes'][0]['rootPage'] = 26988;
$current['rows'] = $rows;
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, $rows);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PayloadHandoffSeed(
    $prepared,
    $current,
    [
        $between('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
        $eq('autoload', 'yes'),
        $eq('blog_id', 1),
        $like('option_name', 'plugin_%'),
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    3,
    0,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next254-269-prepared');
    assert($plan['stat4Next254269PreparationFence']['sliceRange'] === [254, 269]);
    assert($plan['stat4Next254269PreparationFence']['allSlicesPrepared'] === true);
    echo "application-sqlplanner-stat4-expression-partial-current-source-next254-269 self-test passed\n";
}

return [
    'scenario' => 'application-sqlplanner-stat4-expression-partial-current-source-next254-269',
    'status' => $plan['status'],
    'preparedSlices' => $plan['stat4Next254269PreparationFence']['preparedSlices'],
    'handoffSignature' => $plan['stat4Next254269PreparationFence']['handoffSignature'],
    'applicationUse' => 'Copied wp_options plugin pagination can hand off next254-269 STAT4 expression partial planner slices only after next253 payload rows are current-source verified.',
];
