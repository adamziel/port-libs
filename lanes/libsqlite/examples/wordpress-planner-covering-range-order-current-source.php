<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-wp-options-range-order-current-source',
    'schemaCookie' => 118,
    'stat4Generation' => 41,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_value_current_source',
        'rootPage' => 11901,
        'estimatedRows' => 160,
        'stat4Samples' => [
            ['neq' => '1 1 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_current_source ON wp_options(blog_id, autoload, option_name, option_value, option_id) WHERE autoload = 'yes'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-range-order-current-source';
$current['schemaCookie'] = 119;
$current['stat4Generation'] = 42;
$current['indexes'][0]['rootPage'] = 11910;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
    ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
    ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_security']],
];

$plan = SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan::materializeCoveringRangeOrderCurrentSource(
    $prepared,
    $current,
    $and(
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'plugin_z'),
    ),
    [
        ['column' => 'option_name', 'direction' => 'ASC'],
        ['column' => 'option_value', 'direction' => 'ASC'],
    ],
    ['option_name', 'option_value', 'option_id'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'covering-range-order-current-source-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['tableLookupElided'] === true);
    assert($plan['tempSortElided'] === true);
    assert($plan['cursorTape']['matchedKeys'] === ['plugin_cache', 'plugin_forms', 'plugin_security']);
    echo "wordpress-planner-covering-range-order-current-source self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-planner-covering-range-order-current-source',
    'sqlShape' => "SELECT option_name, option_value, option_id FROM wp_options WHERE blog_id = 1 AND autoload = 'yes' AND option_name >= 'plugin_' AND option_name < 'plugin_z' ORDER BY option_name, option_value",
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'rangeColumn' => $plan['selectedPlan']['rangeColumn'] ?? null,
    'tableLookupElided' => $plan['tableLookupElided'],
    'tempSortElided' => $plan['tempSortElided'],
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'wordpressUse' => 'Copied wp_options plugin option scans can reprepare against the current schema/STAT4 source and stream a covering multicolumn range index in ORDER BY order without table rowid lookup or a temp sort, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
