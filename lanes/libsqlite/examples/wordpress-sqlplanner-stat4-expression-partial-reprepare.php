<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-stat4-expression-partial-reprepare',
    'schemaCookie' => 1540,
    'stat4Generation' => 20,
    'rows' => [
        ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_autoload_stat4_stat4-expression-partial-reprepare',
        'rootPage' => 15401,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-stat4-expression-partial-reprepare';
$current['schemaCookie'] = 1544;
$current['stat4Generation'] = 24;
$current['rows'][] = ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh'];
$current['rows'][] = ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy'];
$current['indexes'][0]['rootPage'] = 15431;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
    ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['siteurl', 1]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRepreparedPartialExpressionIndex(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '=', 'right' => 'plugin_cache'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value']
);

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-stat4-expression-partial-reprepare',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'estimatedRows' => $plan['selectedPlan']['estimatedRows'] ?? null,
    'matchedRowids' => $plan['selectedPlan']['matchedRowids'] ?? [],
    'tableLookupRequired' => $plan['tableLookupRequired'],
    'wordpressUse' => 'Preview copied wp_options partial expression-index planning after ANALYZE/stat4 changes without using ext/sqlite; stale prepared plans re-read the current source before scanning option_name plugin rows.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
