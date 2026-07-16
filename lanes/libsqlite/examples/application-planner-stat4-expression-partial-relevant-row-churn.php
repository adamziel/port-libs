<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprIn = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared = [
    'name' => 'prepared-wp-options-copy',
    'schemaCookie' => 1700,
    'stat4Generation' => 41,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache'],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_next170',
        'rootPage' => 17001,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_forms', 11]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-after-plugin-import';
$current['schemaCookie'] = 1704;
$current['stat4Generation'] = 47;
$current['rows'] = [
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'current-cache-a'],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'current-cache-b'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'current-forms'],
    ['rowid' => 23, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Seo', 'option_value' => 'current-seo'],
    ['rowid' => 24, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache'],
];
$current['indexes'][0]['rootPage'] = 17041;
$current['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 20]],
    ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['plugin_forms', 22]],
    ['neq' => '1', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_seo', 23]],
];

$next = $current;
$next['name'] = 'next-wp-options-unrelated-row-churn';
$next['rows'][] = ['rowid' => 25, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache-new'];
$next['rows'][] = ['rowid' => 26, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms-new'];
$next['rows'][] = ['rowid' => 27, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://next.example.test'];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRelevantRowChurn(
    $prepared,
    $current,
    [
        $exprIn('lower(option_name)', ['plugin_cache', 'plugin_forms', 'plugin_seo']),
        $term('autoload', '=', 'yes'),
        $term('blog_id', '=', 1),
        $term('option_name', 'IS NOT NULL'),
    ],
    ['option_name', 'option_value', 'blog_id'],
    $next,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next170-ready');
    assert($plan['next170Source']['admitted'] === true);
    assert($plan['next170Source']['currentRelevantRowids'] === [20, 21, 22, 23]);
    assert($plan['next170Source']['nextRelevantRowids'] === [20, 21, 22, 23]);
    echo "application-planner-stat4-expression-partial-relevant-row-churn self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'],
    'rowids' => $plan['inBucketRowids'],
    'nextSourceAdmitted' => $plan['next170Source']['admitted'],
    'currentRelevantRowids' => $plan['next170Source']['currentRelevantRowids'],
    'nextRelevantRowids' => $plan['next170Source']['nextRelevantRowids'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
