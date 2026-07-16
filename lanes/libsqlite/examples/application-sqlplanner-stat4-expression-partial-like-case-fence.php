<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-application-options-next213',
    'schemaCookie' => 2130,
    'stat4Generation' => 213,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'old-alpha', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_like_case_stat4_next213',
        'rootPage' => 21301,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%', 'caseSensitive' => false, 'collation' => 'NOCASE'],
            ],
            [
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'network_%', 'caseSensitive' => false, 'collation' => 'NOCASE'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-application-options-next213';
$current['schemaCookie'] = 2138;
$current['stat4Generation'] = 269;
$current['indexes'][0]['rootPage'] = 21388;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
    ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
];
$current['rows'] = [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy-b', 'updated_at' => 21],
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeLikeCaseFence(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%', 'caseSensitive' => false, 'collation' => 'NOCASE'],
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    5,
    1,
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next213-ready') {
    throw new RuntimeException('Expected next213 LIKE case/collation contract plan to be ready');
}
if (($plan['likeCaseContractFence']['matchedLikeCaseMode'] ?? null) !== 'NOCASE') {
    throw new RuntimeException('Expected NOCASE LIKE contract to be proven');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'matchedRowids' => $plan['matchedRowids'],
    'likeCaseMode' => $plan['likeCaseContractFence']['matchedLikeCaseMode'],
    'applicationUse' => 'Copied wp_options plugin diagnostics can reuse a current-source STAT4 partial expression index only when the LIKE case/collation mode used by the partial arm still matches the query and selected row payloads.',
], JSON_PRETTY_PRINT) . PHP_EOL;
