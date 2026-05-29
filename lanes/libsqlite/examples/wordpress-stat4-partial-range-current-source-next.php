<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialRangeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => '=', 'left' => ['column' => 'blog_id'], 'right' => 1],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_cache'],
        ['operator' => '<', 'left' => ['column' => 'option_name'], 'right' => 'plugin_seo'],
    ],
];

$prepared = [
    'name' => 'prepared-wp-options-plugin-range',
    'schemaCookie' => 123,
    'stat4Generation' => 44,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_partial_stat4_stable',
        'rootPage' => 12501,
        'estimatedRows' => 120,
        'stat4Samples' => [
            ['neq' => '1 3 3', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_alpha', 'yes']],
            ['neq' => '1 2 2', 'nlt' => '3 3 3', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
            ['neq' => '1 5 5', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
            ['neq' => '1 7 7', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
            ['neq' => '1 2 2', 'nlt' => '17 17 17', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_seo', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_stable ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_' AND option_name < 'plugin_zzzz'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-plugin-range';
$current['schemaCookie'] = 125;
$current['stat4Generation'] = 45;
$current['indexes'][0]['rootPage'] = 12509;
$current['indexes'][0]['estimatedRows'] = 80;
$current['indexes'][0]['sql'] = "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_stable ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_cache' AND option_name < 'plugin_seo'";

$plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare(
    $prepared,
    $current,
    $predicate,
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

$summary = [
    'scenario' => 'wordpress-stat4-partial-range-current-source-stable',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'partialRangeChanged' => $plan['partialRangeChanged'],
    'preparedPartialRange' => $plan['preparedSource']['partialRange'],
    'currentPartialRange' => $plan['currentSource']['partialRange'],
    'preparedWouldUseStalePartialRange' => $plan['preparedWouldUseStalePartialRange'],
    'currentEstimate' => $plan['currentRowEstimate'],
    'detail' => $plan['detail'],
    'wordpressUse' => 'Preview copied wp_options plugin-option imports after ANALYZE narrows a partial STAT4 index; the planner reparses so stale prepared range bounds do not admit rows outside the current partial index.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'usable');
    assert($summary['selectedSource'] === 'current');
    assert($summary['partialRangeChanged'] === true);
    assert($summary['preparedWouldUseStalePartialRange'] === true);
    assert($summary['currentPartialRange']['lower'] === 'plugin_cache');
    echo "wordpress-stat4-partial-range-current-source-stable self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
