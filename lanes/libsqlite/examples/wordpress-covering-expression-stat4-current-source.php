<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan;

$lower = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => $lower, 'right' => 'plugin_'],
        ['operator' => '<', 'left' => $lower, 'right' => 'plugin_z'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$prepared = [
    'name' => 'prepared-wp-options-covering-expression-stat4-canonical',
    'schemaCookie' => 1220,
    'stat4Generation' => 31,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_covering_stat4_canonical',
        'rootPage' => 12201,
        'estimatedRows' => 360,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [$lower],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
            ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
            ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 103]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_canonical ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-covering-expression-stat4-canonical';
$current['schemaCookie'] = 1224;
$current['stat4Generation'] = 35;
$current['indexes'][0]['rootPage'] = 12244;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 301]],
    ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 302]],
    ['neq' => '3 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 303]],
    ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 304]],
    ['neq' => '4 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 305]],
];
$rows = [
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
];

$plan = SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $predicate,
    $rows,
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'covering-expression-stat4-current-source-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['tableLookupElided'] === true);
    assert($plan['cursorTape']['matchedKeys'] === ['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo']);
    echo "wordpress-covering-expression-stat4-current-source-canonical self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-covering-expression-stat4-current-source-canonical',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'wordpressUse' => 'Preview copied wp_options plugin option scans after ANALYZE refresh: stale prepared expression-index plans reprepare to the current STAT4 source and keep option payload reads on the covering index cursor.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
