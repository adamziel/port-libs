<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-application-options-next190',
    'schemaCookie' => 1900,
    'stat4Generation' => 64,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_not_in_next190',
        'rootPage' => 19001,
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
$current['name'] = 'current-application-options-next190';
$current['schemaCookie'] = 1909;
$current['stat4Generation'] = 86;
$current['indexes'][0]['rootPage'] = 19088;
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

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceTrailingPayloadFence(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'LIKE', 'right' => 'plugin\_%', 'escape' => '\\'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => 'NOT IN', 'right' => ['values' => ['plugin_debug_trace', 'plugin_tmp_cache']]],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
    ],
    ['option_name', 'option_value'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next190-ready') {
    throw new RuntimeException('Expected next190 STAT4 expression partial NOT IN residual plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [10, 20]) {
    throw new RuntimeException('Expected debug/tmp options to be filtered after STAT4 prefix admission');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'beforeResidual' => $plan['matchedRowidsBeforeNotInResidual'],
    'afterResidual' => $plan['matchedRowids'],
    'rejected' => $plan['notInResidualRowidsRejected'],
], JSON_PRETTY_PRINT) . PHP_EOL;
