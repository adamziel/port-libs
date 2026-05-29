<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprEq = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];
$exprIn = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-next161',
    'schemaCookie' => 1610,
    'stat4Generation' => 44,
    'rows' => [
        ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache', 'updated_at' => 10],
        ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 36, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_or_partial_stat4_next161',
        'rootPage' => 16101,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'blog_id', 'autoload'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 12]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 24]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 36]],
        ],
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-next161';
$current['schemaCookie'] = 1618;
$current['stat4Generation'] = 55;
$current['indexes'][0]['rootPage'] = 16188;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 12]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 24]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 48]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 36]],
];
$current['rows'] = [
    ['rowid' => 48, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 40],
    ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'new-cache', 'updated_at' => 15],
    ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 36, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 72, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
];
$arms = [[
    $exprEq('LOWER( option_name )', 'plugin_cache'),
    $eq('blog_id', 1),
    $eq('autoload', 'yes'),
    $notNull('option_name'),
], [
    $exprIn('lower(option_name)', ['plugin_forms', 'plugin_mail', 'plugin_seo']),
    $eq('blog_id', 1),
    $eq('autoload', 'yes'),
    $notNull('option_name'),
]];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext161(
    $prepared,
    $current,
    $arms,
    ['option_name', 'option_value', 'updated_at'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next161-ready');
    assert($plan['matchedRowids'] === [12, 24, 48, 36]);
    assert($plan['orArmPlans'][1]['matchedStat4Keys'] === ['plugin_forms', 'plugin_mail', 'plugin_seo']);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next161 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next161',
    'wordpressUse' => 'Preview copied wp_options plugin diagnostics whose OR-split lower(option_name) probes can use the current partial STAT4 expression index only when every arm proves blog/autoload partial predicates after ANALYZE/source changes.',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'selectedSource' => $plan['selectedSource'],
    'matchedRowids' => $plan['matchedRowids'],
    'matchedKeys' => $plan['matchedExpressionKeys'],
    'orArmKeys' => array_column($plan['orArmPlans'], 'matchedStat4Keys'),
    'tableLookupRequired' => $plan['tableLookupRequired'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
