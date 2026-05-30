<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next180',
    'schemaCookie' => 1800,
    'stat4Generation' => 70,
    'rows' => [
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_partial_stat4_next180',
        'rootPage' => 18001,
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
        'coveringColumns' => ['option_name', 'option_value', 'autoload'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next180';
$current['schemaCookie'] = 1808;
$current['stat4Generation'] = 88;
$current['indexes'][0]['rootPage'] = 18088;
$current['rows'] = [
    ['rowid' => 60, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu'],
    ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
    ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha'],
    ['rowid' => 70, 'autoload' => 'no', 'option_name' => 'plugin_zulu', 'option_value' => 'lazy'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4DescendingBoundaryFence(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value'],
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next180-ready') {
    throw new RuntimeException('Expected next180 descending STAT4 expression partial plan to be ready');
}
if (($plan['matchedRowids'] ?? []) !== [60, 30, 20, 10]) {
    throw new RuntimeException('Expected WordPress plugin option scan to use descending current-source rowids');
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'descendingFence' => $plan['descendingFence'],
    'matchedRowids' => $plan['matchedRowids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
