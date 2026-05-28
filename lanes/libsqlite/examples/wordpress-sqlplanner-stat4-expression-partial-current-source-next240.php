<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
    'name' => 'prepared-wp-options-next240',
    'schemaCookie' => 2400,
    'stat4Generation' => 240,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10, 'kind' => 'plugin'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20, 'kind' => 'plugin'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-old', 'updated_at' => 30, 'kind' => 'plugin'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_current_partial_next240',
        'rootPage' => 24001,
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
            ['neq' => '2 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '3 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-next240';
$current['schemaCookie'] = 2409;
$current['stat4Generation'] = 340;
$current['indexes'][0]['rootPage'] = 24088;
$current['indexes'][0]['partialPredicateTerms'] = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_tango'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
];
$current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1 1 1', 'nlt' => '4 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_seo', 40, 'yes', 1]];
$current['rows'] = [
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40, 'kind' => 'extension'],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail', 'updated_at' => 30, 'kind' => 'extension'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 20, 'kind' => 'extension'],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy', 'updated_at' => 21, 'kind' => 'extension'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10, 'kind' => 'extension'],
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, $current['rows']);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan::materialize(
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
    4,
    0,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next240-ready');
    assert($plan['stat4CurrentPartialPredicateFence']['currentPartialPredicateImplied'] === true);
    assert($plan['stat4CurrentPartialPredicateFence']['stalePreparedOnlyPredicatesUsed'] === []);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next240 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next240',
    'wordpressUse' => 'Copied wp_options plugin scans can reuse a current STAT4 partial expression index only after proving current partial predicates and rejecting stale prepared-only predicate terms.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'currentOnlyPredicates' => $plan['stat4CurrentPartialPredicateFence']['currentOnlyPredicates'],
    'preparedOnlyPredicates' => $plan['stat4CurrentPartialPredicateFence']['preparedOnlyPredicates'],
    'matchedRowids' => $plan['matchedRowids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
