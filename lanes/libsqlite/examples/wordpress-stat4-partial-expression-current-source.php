<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan;

$lower = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => $lower, 'right' => 'plugin_'],
        ['operator' => '<', 'left' => $lower, 'right' => 'plugin_z'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$preparedSource = [
    'name' => 'prepared-wp-options-current-source',
    'schemaCookie' => 1330,
    'stat4Generation' => 40,
    'rowGeneration' => 10,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_expr_current_source',
        'rootPage' => 13301,
        'estimatedRows' => 360,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [$lower],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 21]],
            ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 31]],
            ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 51]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_partial_expr_current_source ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$currentSource = $preparedSource;
$currentSource['name'] = 'current-wp-options-current-source';
$currentSource['schemaCookie'] = 1334;
$currentSource['stat4Generation'] = 47;
$currentSource['rowGeneration'] = 16;
$currentSource['indexes'][0]['rootPage'] = 13344;
$currentSource['indexes'][0]['stat4Samples'][] = ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 41]];
$preparedRows = [
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-old', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_deleted', 'autoload' => 'yes', 'option_value' => 'deleted', 'option_id' => 81, 'blog_id' => 1],
];
$currentRows = [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-new', 'option_id' => 21, 'blog_id' => 1],
];

$plan = SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan::materialize(
    $preparedSource,
    $currentSource,
    $predicate,
    $preparedRows,
    $currentRows,
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'partial-expression-stat4-current-source-ready');
    assert($plan['rowGenerationFence']['deletedRowids'] === [81]);
    assert($plan['cursorTape']['matchedRowids'] === [21, 31, 41]);
    assert($plan['currentNextRows'][0]['current']['covering']['option_value'] === 'cache-new');
    echo "wordpress-stat4-partial-expression-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-stat4-partial-expression-current-source',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'matchedRowids' => $plan['cursorTape']['matchedRowids'],
    'deletedPreparedRowidsBlocked' => $plan['rowGenerationFence']['deletedRowids'],
    'updatedCurrentRowidsRefreshed' => $plan['rowGenerationFence']['updatedRowids'],
    'wordpressUse' => 'Preview copied wp_options plugin-option scans after ANALYZE and row churn: stale partial expression-index plans reprepare to current STAT4 samples, block deleted prepared payload rows, and keep refreshed autoloaded option payloads on the covering cursor.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
