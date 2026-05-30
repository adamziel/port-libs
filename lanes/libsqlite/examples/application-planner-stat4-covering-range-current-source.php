<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows = [
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-a'],
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-a'],
    ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-b'],
    ['rowid' => 16, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-a'],
    ['rowid' => 17, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-b'],
];
$indexSql = 'CREATE INDEX idx_wp_options_blog_autoload_name_cover_stat4_currentSourceCursor ON wp_options(blog_id, autoload, option_name, option_value, rowid)';
$source = static fn (array $sourceRows, int $cookie, int $stat4, int $root, array $samples): array => [
    'name' => 'wp-options-stat4-covering-range-currentSourceCursor-' . $cookie,
    'schemaCookie' => $cookie,
    'stat4Generation' => $stat4,
    'rows' => $sourceRows,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_cover_stat4_currentSourceCursor',
        'rootPage' => $root,
        'estimatedRows' => 80,
        'stat4Samples' => $samples,
        'sql' => $indexSql,
    ]],
];

$currentSamples = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
    ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
    ['neq' => '1 1 2', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_seo']],
];
$preparedSamples = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_legacy']],
    ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
    ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
];

$plan = SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan::materializeCurrentSourceCursor(
    $source(array_slice($rows, 0, 3), 1400, 90, 14001, $preparedSamples),
    $source($rows, 1401, 91, 14041, $currentSamples),
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
    assert($plan['status'] === 'stat4-covering-range-current-source-cursor-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['currentSourceNextCursor']['rowids'] === [10, 11, 12, 16, 17]);
    assert($plan['rangeDuplicateRowids'] === [11, 12, 16, 17]);
    assert($plan['currentSourceNextCursor']['stalePreparedBucketsRejected'] === ['plugin_legacy']);
    echo "application-planner-stat4-covering-range-current-source-cursor self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'rowids' => $plan['currentSourceNextCursor']['rowids'],
    'duplicateRowids' => $plan['rangeDuplicateRowids'],
    'currentBoundaries' => $plan['stat4CurrentBoundaryKeys'],
    'rejectedPreparedBoundaries' => $plan['currentSourceNextCursor']['stalePreparedBucketsRejected'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
