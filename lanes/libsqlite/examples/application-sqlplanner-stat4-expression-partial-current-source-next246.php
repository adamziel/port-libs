<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
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
    'name' => 'prepared-wp-options-next246',
    'schemaCookie' => 2460,
    'stat4Generation' => 246,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10, 'kind' => 'plugin'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20, 'kind' => 'plugin'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-old', 'updated_at' => 30, 'kind' => 'plugin'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_cardinality_next246',
        'rootPage' => 24601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['column' => 'kind'], 'operator' => '=', 'right' => 'plugin'],
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
            ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '2 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-next246';
$current['schemaCookie'] = 2469;
$current['stat4Generation'] = 346;
$current['indexes'][0]['rootPage'] = 24688;
$current['indexes'][0]['partialPredicateTerms'] = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_tango'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
];
$current['rows'] = [
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40, 'kind' => 'extension'],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail', 'updated_at' => 30, 'kind' => 'extension'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 20, 'kind' => 'extension'],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21, 'kind' => 'extension'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22, 'kind' => 'extension'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10, 'kind' => 'extension'],
];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
    ['neq' => '3 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
    ['neq' => '1 1 1 1', 'nlt' => '4 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
    ['neq' => '1 1 1 1', 'nlt' => '5 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_seo', 40, 'yes', 1]],
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, $current['rows']);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicateCardinalityValidation(
    $prepared,
    $current,
    [
        $between('LOWER(option_name)', 'plugin_alpha', 'plugin_tango'),
        $term('autoload', 'yes'),
        $notNull('option_name'),
        $term('blog_id', 1),
        ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    ['autoload', 'blog_id'],
    6,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next246-ready');
    assert($plan['stat4DuplicateCardinalityFence']['duplicateExpressionKeys'] === ['plugin_forms']);
    assert($plan['stat4DuplicateCardinalityFence']['stat4ExpressionCounts']['plugin_forms'] === 3);
    echo "application-sqlplanner-stat4-expression-partial-current-source-next246 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-sqlplanner-stat4-expression-partial-current-source-next246',
    'applicationUse' => 'Copied wp_options plugin scans can reuse a current STAT4 partial expression index only after current sqlite_stat4 neq duplicate counts match the duplicate option_name expression buckets.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'duplicateExpressionKeys' => $plan['stat4DuplicateCardinalityFence']['duplicateExpressionKeys'],
    'actualExpressionCounts' => $plan['stat4DuplicateCardinalityFence']['actualExpressionCounts'],
    'stat4ExpressionCounts' => $plan['stat4DuplicateCardinalityFence']['stat4ExpressionCounts'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
