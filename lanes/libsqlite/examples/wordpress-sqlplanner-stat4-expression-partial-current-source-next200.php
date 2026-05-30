<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$like = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => '\\'];
$notBetween = static fn (string $expression, string $lower, string $upper): array => ['left' => ['expression' => $expression], 'operator' => 'NOT BETWEEN', 'values' => [['literal' => $lower], ['literal' => $upper]]];
$columnNotBetween = static fn (string $column, string $lower, string $upper): array => ['left' => ['column' => $column], 'operator' => 'NOT BETWEEN', 'lower' => ['literal' => $lower], 'upper' => ['literal' => $upper]];
$range = static fn (string $expression, string $operator, string $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-not-between-next200',
    'schemaCookie' => 2000,
    'stat4Generation' => 75,
    'rows' => [
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'aa-cache-old'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'mm-forms-old'],
        ['rowid' => 33, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'zz-search-old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_not_between_next200',
        'rootPage' => 20001,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            $eq('blog_id', 1),
            $eq('autoload', 'yes'),
            $notNull('option_name'),
            $range('lower(option_name)', '>=', 'plugin_'),
            $range('lower(option_name)', '<', 'plugin`'),
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_search', 33]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-not-between-next200';
$current['schemaCookie'] = 2011;
$current['stat4Generation'] = 96;
$current['indexes'][0]['rootPage'] = 20088;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 44]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 22]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 55]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_search', 66]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin_seo', 77]],
];
$current['rows'] = [
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'aa-cache-current'],
    ['rowid' => 44, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'bb-debug-current'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'mm-forms-current'],
    ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'nn-mail-current'],
    ['rowid' => 66, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'zz-search-current'],
    ['rowid' => 77, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'zz-seo-current'],
];

$terms = [
    $like('LOWER( option_name )', 'plugin\_%'),
    $notBetween('lower(option_name)', 'plugin_debug', 'plugin_mail'),
    $columnNotBetween('option_value', 'aa', 'cc'),
    $eq('blog_id', 1),
    $eq('autoload', 'yes'),
    $notNull('option_name'),
    $range('lower(option_name)', '>=', 'plugin_'),
    $range('lower(option_name)', '<', 'plugin`'),
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceHistogramFence(
    $prepared,
    $current,
    $terms,
    ['option_name', 'option_value'],
);

$summary = [
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next200',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'beforeResidualRowids' => $plan['matchedRowidsBeforeNotBetweenResidual'],
    'afterResidualRowids' => $plan['matchedRowids'],
    'acceptedKeys' => $plan['matchedExpressionKeys'],
    'rejectedRowids' => $plan['notBetweenResidualRowidsRejected'],
    'wordpressUse' => 'Copied wp_options plugin scans can reuse a partial lower(option_name) STAT4 prefix window while preserving NOT BETWEEN residual checks against current-source rows after ANALYZE refresh.',
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'stat4-expression-partial-current-source-next200-ready') {
        throw new RuntimeException('Expected next200 STAT4 NOT BETWEEN residual plan to be ready');
    }
    if ($summary['afterResidualRowids'] !== [66, 77]) {
        throw new RuntimeException('Expected only plugin_search and plugin_seo to survive NOT BETWEEN residuals');
    }
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next200 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
