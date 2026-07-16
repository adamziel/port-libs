<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-application-options',
    'schemaCookie' => 1680,
    'stat4Generation' => 81,
    'rows' => [
        ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-cache_alpha', 'option_value' => 'old-cache', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_like_partial_stat4',
        'rootPage' => 16801,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin-cache_alpha', 1]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-application-options';
$current['schemaCookie'] = 1689;
$current['stat4Generation'] = 92;
$current['indexes'][0]['rootPage'] = 16888;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin-cache_alpha', 21]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin-forms', 22]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin-mailer', 23]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['theme_mods', 24]],
];
$current['rows'] = [
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-cache_alpha', 'option_value' => 'cache', 'updated_at' => 15],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin-forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 23, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin-Mailer', 'option_value' => 'mail', 'updated_at' => 30],
    ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme', 'updated_at' => 40],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4LikePrefixPartialCurrentSource(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER( option_name )'], 'operator' => 'LIKE', 'right' => 'plugin-%'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value', 'updated_at'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next168-ready') {
    throw new RuntimeException('Expected next168 STAT4 expression partial LIKE-prefix plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [21, 22, 23]) {
    throw new RuntimeException('Expected Application plugin option rows to use the current-source LIKE-prefix STAT4 stream');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'prefixFence' => $plan['prefixFence'],
    'matchedRowids' => $plan['matchedRowids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
