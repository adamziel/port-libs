<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next195',
    'schemaCookie' => 1950,
    'stat4Generation' => 12,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_predicate_stat4_next195',
        'rootPage' => 19501,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 10]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next195';
$current['schemaCookie'] = 1958;
$current['stat4Generation'] = 24;
$current['indexes'][0]['rootPage'] = 19588;
$current['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
    ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 40]],
];
$current['rows'] = [
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 40],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 20],
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 11],
    ['rowid' => 50, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 50],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePartialPredicateFence(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    3,
    0,
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next195-ready') {
    throw new RuntimeException('Expected next195 STAT4 expression partial predicate fence to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [40, 30, 20]) {
    throw new RuntimeException('Expected current-source WordPress plugin rows to survive the changed partial predicate fence');
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next195',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'partialPredicateChanged' => $plan['partialPredicateFence']['partialPredicateChanged'],
    'matchedRowids' => $plan['matchedRowids'],
    'wordpressUse' => 'Copied wp_options plugin diagnostics can safely reuse a current-source STAT4 partial expression index after ANALYZE/schema changes only when the changed partial WHERE predicate is proven by query terms and selected rows.',
], JSON_PRETTY_PRINT) . PHP_EOL;
