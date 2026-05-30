<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next187',
    'schemaCookie' => 1870,
    'stat4Generation' => 54,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_not_like_next187',
        'rootPage' => 18701,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next187';
$current['schemaCookie'] = 1879;
$current['stat4Generation'] = 77;
$current['indexes'][0]['rootPage'] = 18788;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 40]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_tmp_cache', 60]],
];
$current['rows'] = [
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-current'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_tmp_cache', 'option_value' => 'tmp-current'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceNotLikeResidualFence(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'LIKE', 'right' => 'plugin\_%', 'escape' => '\\'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'NOT LIKE', 'right' => 'plugin\_debug%', 'escape' => '\\'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'NOT LIKE', 'right' => 'plugin\_tmp%', 'escape' => '\\'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
    ],
    ['option_name', 'option_value'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next187-ready') {
    throw new RuntimeException('Expected next187 STAT4 expression partial NOT LIKE residual plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [10, 20]) {
    throw new RuntimeException('Expected debug/tmp options to be filtered after STAT4 prefix admission');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'beforeResidual' => $plan['matchedRowidsBeforeNotLikeResidual'],
    'afterResidual' => $plan['matchedRowids'],
    'rejected' => $plan['notLikeResidualRowidsRejected'],
], JSON_PRETTY_PRINT) . PHP_EOL;
