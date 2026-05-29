<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next177',
    'schemaCookie' => 1770,
    'stat4Generation' => 50,
    'rows' => [
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_between_partial_stat4_next177',
        'rootPage' => 17701,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_cache'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_seo'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next177';
$current['schemaCookie'] = 1778;
$current['stat4Generation'] = 68;
$current['indexes'][0]['rootPage'] = 17788;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
];
$current['rows'] = [
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
    ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'fresh-cache-copy', 'updated_at' => 16],
    ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 40],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext177(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER( option_name )'], 'operator' => 'BETWEEN', 'lower' => 'plugin_cache', 'upper' => 'plugin_seo'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value', 'updated_at'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next177-ready') {
    throw new RuntimeException('Expected next177 STAT4 expression partial BETWEEN plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [10, 11, 20, 30]) {
    throw new RuntimeException('Expected WordPress plugin option BETWEEN scan to include inclusive STAT4 boundaries from current source');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'betweenFence' => $plan['betweenFence'],
    'matchedRowids' => $plan['matchedRowids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
