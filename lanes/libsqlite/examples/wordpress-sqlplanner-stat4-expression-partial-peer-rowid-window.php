<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wordpress-options-next210',
    'schemaCookie' => 2100,
    'stat4Generation' => 210,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_rowid_stat4_next210',
        'rootPage' => 21001,
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
        ],
        'coveringColumns' => ['option_name', 'option_value', 'blog_id', 'autoload'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wordpress-options-next210';
$current['schemaCookie'] = 2108;
$current['stat4Generation'] = 256;
$current['indexes'][0]['rootPage'] = 21088;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_seo', 30]],
    ['neq' => '3 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
];
$current['rows'] = [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu'],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b'],
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePeerRowidWindow(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ],
    ['option_name', 'option_value', 'blog_id'],
    5,
    1,
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next210-ready') {
    throw new RuntimeException('Expected next210 STAT4 expression partial peer rowid order plan to be ready');
}
if (($plan['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['rowids'] ?? null) !== [20, 21, 22]) {
    throw new RuntimeException('Expected duplicate lower(option_name) peers to keep SQLite rowid tie-break order');
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next210',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'matchedRowids' => $plan['matchedRowids'],
    'duplicatePeerKeys' => $plan['expressionPeerOrderFence']['duplicateExpressionPeerKeys'],
    'duplicatePeerRowids' => $plan['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['rowids'],
    'wordpressUse' => 'Copied wp_options diagnostics can reprepare a stale STAT4 partial expression index and preserve SQLite rowid tie-break order for duplicate lower(option_name) plugin rows without ext/sqlite.',
], JSON_PRETTY_PRINT) . PHP_EOL;
