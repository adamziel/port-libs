<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedRows = [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'a:2'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'a:3'],
];
$currentRows = array_merge($preparedRows, [
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'a:4'],
]);

$indexSql = "CREATE INDEX idx_wp_options_blog_autoload_plugin_covering_next131 ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_'";
$prepared = [
    'name' => 'prepared-wp-options-copy',
    'schemaCookie' => 1310,
    'stat4Generation' => 7,
    'rows' => $preparedRows,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_next131',
        'rootPage' => 91,
        'estimatedRows' => 20,
        'sql' => $indexSql,
    ]],
];
$current = [
    'name' => 'current-wp-options-after-plugin-import',
    'schemaCookie' => 1311,
    'stat4Generation' => 8,
    'rows' => $currentRows,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_next131',
        'rootPage' => 94,
        'estimatedRows' => 12,
        'sql' => $indexSql,
    ]],
];

$plan = SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan::materializeNext131(
    $prepared,
    $current,
    $and(
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'plugin_z'),
    ),
    ['option_name', 'option_value', 'rowid'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'covering-partial-range-current-source-ready');
    assert($plan['selectedSource'] === 'current');
    assert(array_column($plan['coveredRows'], 'rowid') === [1, 2, 4]);
    assert($plan['selectedPlan']['tableLookupRequired'] === false);
    echo "wordpress-planner-covering-partial-range-current-source-next131 self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'],
    'rootPage' => $plan['selectedPlan']['rootPage'],
    'coveredRowids' => array_column($plan['coveredRows'], 'rowid'),
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
