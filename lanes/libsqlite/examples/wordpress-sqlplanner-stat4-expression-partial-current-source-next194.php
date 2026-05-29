<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next194',
    'schemaCookie' => 1940,
    'stat4Generation' => 70,
    'rows' => [
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_distinct_next194',
        'rootPage' => 19401,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next194';
$current['schemaCookie'] = 1951;
$current['stat4Generation'] = 91;
$current['indexes'][0]['rootPage'] = 19488;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 44]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 22]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_nullish', 66]],
];
$current['rows'] = [
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
    ['rowid' => 44, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-current'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
    ['rowid' => 66, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_nullish', 'option_value' => null],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext194(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'LIKE', 'right' => 'plugin\_%', 'escape' => '\\'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'IS DISTINCT FROM', 'right' => ['literal' => 'plugin_debug_trace']],
        ['left' => ['column' => 'option_value'], 'operator' => 'IS DISTINCT FROM', 'right' => ['literal' => null]],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
    ],
    ['option_name', 'option_value'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next194-ready') {
    throw new RuntimeException('Expected next194 STAT4 expression partial IS DISTINCT FROM residual plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [11, 22]) {
    throw new RuntimeException('Expected debug/null option rows to be filtered after STAT4 prefix admission');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'beforeResidual' => $plan['matchedRowidsBeforeIsDistinctResidual'],
    'afterResidual' => $plan['matchedRowids'],
    'rejected' => $plan['isDistinctFromResidualRowidsRejected'],
], JSON_PRETTY_PRINT) . PHP_EOL;
