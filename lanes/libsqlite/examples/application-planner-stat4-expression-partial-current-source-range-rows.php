<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprRange = static fn (string $expression, string $operator, string $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-copy',
    'schemaCookie' => 1740,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache', 'updated_at' => 10],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms', 'updated_at' => 11],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_partial_range_rows',
        'rootPage' => 17401,
        'expression' => 'lower(option_name)',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 11]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 12]],
            ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 13]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-after-plugin-import';
$current['schemaCookie'] = 1745;
$current['stat4Generation'] = 44;
$current['indexes'][0]['rootPage'] = 17445;
$current['rows'] = [
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-a', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-b', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 22],
    ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 24],
    ['rowid' => 26, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Seo', 'option_value' => 'seo', 'updated_at' => 26],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'updated_at' => 30],
];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 20]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 24]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 26]],
];

$next = $current;
$next['name'] = 'next-wp-options-outside-plugin-range-churn';
$next['rows'][] = ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://next.example.test', 'updated_at' => 40];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRangeRows(
    $prepared,
    $current,
    [
        $exprRange('LOWER( option_name )', '>=', 'plugin_cache'),
        $exprRange('lower(option_name)', '<', 'plugin_t'),
        $term('autoload', '=', 'yes'),
        $term('blog_id', '=', 1),
        $term('option_name', 'IS NOT NULL'),
    ],
    ['option_name', 'option_value', 'updated_at'],
    $next,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-range-rows-ready');
    assert($plan['rangeRowsSource']['admitted'] === true);
    assert($plan['matchedRowids'] === [20, 21, 22, 24, 26]);
    echo "application-planner-stat4-expression-partial-current-source-range-rows self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'],
    'range' => [$plan['selectedPlan']['rangeLower'], $plan['selectedPlan']['rangeUpper']],
    'matchedRowids' => $plan['matchedRowids'],
    'nextSourceAdmitted' => $plan['rangeRowsSource']['admitted'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
