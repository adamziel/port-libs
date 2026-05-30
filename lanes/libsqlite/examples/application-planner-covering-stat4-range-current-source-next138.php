<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedRows = [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
];
$currentRows = array_merge($preparedRows, [
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'search'],
]);
$indexSql = 'CREATE INDEX idx_wp_options_blog_autoload_name_cover_stat4_next138 ON wp_options(blog_id, autoload, option_name, option_value, rowid)';
$source = static fn (array $rows, int $cookie, int $stat4, int $root): array => [
    'name' => 'wp-options-stat4-range-source-' . $cookie,
    'schemaCookie' => $cookie,
    'stat4Generation' => $stat4,
    'rows' => $rows,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_cover_stat4_next138',
        'rootPage' => $root,
        'estimatedRows' => 40,
        'stat4Samples' => [
            ['neq' => '1 1 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 2', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_security']],
            ['neq' => '1 1 2', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_seo']],
        ],
        'sql' => $indexSql,
    ]],
];

$plan = SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan::materializeCoveringStat4Range(
    $source($preparedRows, 1380, 80, 13801),
    $source($currentRows, 1381, 81, 13811),
    $and(
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_cache'),
        $range('option_name', '<=', 'plugin_seo'),
    ),
    [['column' => 'option_name']],
    ['option_name', 'option_value', 'rowid'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'covering-stat4-range-current-source-next138-ready');
    assert($plan['selectedSource'] === 'current');
    assert(array_column($plan['coveredRows'], 'rowid') === [1, 2, 3, 4]);
    assert($plan['tableLookupElided'] === true);
    assert(array_column($plan['stat4RangeBuckets'], 'key') === ['plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo']);
    echo "application-planner-covering-stat4-range-current-source-next138 self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'],
    'coveredRowids' => array_column($plan['coveredRows'], 'rowid'),
    'stat4RangeKeys' => array_column($plan['stat4RangeBuckets'], 'key'),
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
