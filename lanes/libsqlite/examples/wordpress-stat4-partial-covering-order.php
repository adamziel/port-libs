<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_alpha'),
        $range('option_name', '<=', 'plugin_seo'),
    ],
];
$rows = [
    ['rowid' => 2, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'active', 'updated_at' => 40],
    ['rowid' => 3, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'warm', 'updated_at' => 60],
    ['rowid' => 4, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 70],
    ['rowid' => 5, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 30],
    ['rowid' => 6, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy', 'updated_at' => 50],
    ['rowid' => 7, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield', 'updated_at' => 80],
    ['rowid' => 8, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'search', 'updated_at' => 20],
    ['rowid' => 9, 'blog_id' => 2, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 90],
];
$prepared = [
    'name' => 'prepared-wp-options-stat4-partial-covering-next142',
    'schemaCookie' => 1420,
    'stat4Generation' => 80,
    'coveringColumns' => ['autoload', 'option_value', 'updated_at', 'rowid'],
    'rows' => array_slice($rows, 0, 4),
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_stat4_cover_next142',
        'rootPage' => 14201,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_alpha', 'yes']],
            ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next142 ON wp_options(blog_id, option_name, autoload, option_value, updated_at, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-stat4-partial-covering-next142';
$current['schemaCookie'] = 1422;
$current['stat4Generation'] = 84;
$current['rows'] = $rows;
$current['indexes'][0]['rootPage'] = 14222;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_alpha', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '3 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '5 3 3', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '8 4 4', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_seo', 'yes']],
];

$plan = SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan::materializePartialCoveringOrder(
    $prepared,
    $current,
    $predicate,
    [['column' => 'updated_at', 'direction' => 'DESC']],
    ['autoload', 'option_value', 'updated_at', 'rowid'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'stat4-partial-covering-current-source-next142-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['tempBtreeForRightPartOrderBy'] === true);
    assert(array_column($plan['coveredRows'], 'rowid') === [7, 4, 3, 2, 5, 8]);
    echo "wordpress-stat4-partial-covering-order self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-stat4-partial-covering-order',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'orderedRowids' => array_column($plan['coveredRows'], 'rowid'),
    'stat4Blocks' => $plan['stat4AnchorBlockCount'],
    'rightPartSort' => $plan['tempBtreeForRightPartOrderBy'],
    'wordpressUse' => 'Copied wp_options plugin autoload scans can reprepare stale partial-covering STAT4 plans, keep option payload reads on the index cursor, and sort current/next rows by a dashboard freshness column without table lookups.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
