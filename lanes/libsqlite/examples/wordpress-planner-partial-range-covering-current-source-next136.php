<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';

use PortLibs\LibSqlite\SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-wp-options-plugin-range-next136',
    'schemaCookie' => 1360,
    'stat4Generation' => 60,
    'rows' => [
        ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1'],
        ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'theme', 'option_name' => 'plugin_cache', 'option_value' => 'theme-cache'],
        ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_forms', 'option_value' => 'a:2'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_plugin_kind_range_covering_next136',
        'rootPage' => 13601,
        'estimatedRows' => 80,
        'sql' => "CREATE INDEX idx_wp_options_plugin_kind_range_covering_next136 ON wp_options(blog_id, autoload, option_name, option_value, kind, rowid) WHERE kind = 'plugin' AND autoload = 'yes' AND option_name >= 'plugin_'",
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-plugin-range-next136';
$current['schemaCookie'] = 1361;
$current['stat4Generation'] = 61;
$current['rows'][] = ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'mu-plugin', 'option_name' => 'plugin_mu', 'option_value' => 'mu'];
$current['rows'][] = ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_security', 'option_value' => 'a:5'];

$plan = SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan::materializeNext136(
    $prepared,
    $current,
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'plugin_z'),
    ),
    ['option_name', 'option_value', 'kind', 'rowid'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'partial-range-covering-current-source-ready');
    assert(array_column($plan['coveredRows'], 'rowid') === [2, 4, 8]);
    assert($plan['partialPredicateFilteredRowids'] === [3, 7]);
    echo "wordpress-planner-partial-range-covering-current-source-next136 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-planner-partial-range-covering-current-source-next136',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'coveredRowids' => array_column($plan['coveredRows'], 'rowid'),
    'filteredRowids' => $plan['partialPredicateFilteredRowids'],
    'detail' => $plan['detail'],
    'wordpressUse' => 'Copied wp_options plugin scans can reprepare a stale partial covering range plan against the current source and avoid leaking theme or mu-plugin rows that fall inside the raw option_name range.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
