<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4OrderCoveringCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-before-plugin-analyze',
    'schemaCookie' => 94,
    'stat4Generation' => 18,
    'coveringColumns' => ['autoload', 'option_name', 'option_value'],
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_value_stat4_current_source',
        'rootPage' => 9401,
        'estimatedRows' => 150,
        'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 120],
        'stat4Samples' => [
            ['neq' => '1 8 8 8', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
            ['neq' => '1 14 14 14', 'nlt' => '8 8 8 8', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
            ['neq' => '1 25 25 25', 'nlt' => '22 22 22 22', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_current_source ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-after-plugin-import-analyze';
$current['schemaCookie'] = 95;
$current['stat4Generation'] = 19;
$current['indexes'][0]['rootPage'] = 9410;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 2 2 2', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
    ['neq' => '1 4 4 4', 'nlt' => '2 2 2 2', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_cache', 'a:2:{}']],
    ['neq' => '1 5 5 5', 'nlt' => '6 6 6 6', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_forms', 'a:3:{}']],
    ['neq' => '1 3 3 3', 'nlt' => '11 11 11 11', 'ndlt' => '3 3 3 3', 'sample' => [1, 'yes', 'plugin_security', 'a:4:{}']],
    ['neq' => '1 2 2 2', 'nlt' => '14 14 14 14', 'ndlt' => '4 4 4 4', 'sample' => [1, 'yes', 'plugin_slider', 'a:5:{}']],
];

$plan = SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan(
    $prepared,
    $current,
    $and(
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'plugin_z')
    ),
    [['column' => 'option_name'], ['column' => 'option_value']],
    ['option_name', 'option_value', 'autoload'],
);

$output = [
    'scenario' => 'application-planner-stat4-order-covering-current-source',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'coveringOrderPlan' => $plan['coveringOrderPlan'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'tempSortElided' => $plan['tempSortElided'],
    'currentRows' => $plan['currentSource']['estimatedRows'] ?? null,
    'rangeCurrentNext' => $plan['selectedPlan']['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied multisite wp_options plugin scans where ANALYZE refreshes sqlite_stat4 and a stale prepared ORDER BY plan must be rebound to the current covering index before plugin import cleanup queries run.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedSource'] ?? null) !== 'current' || ($output['reprepareRequired'] ?? null) !== true) {
        fwrite(STDERR, "expected current-source STAT4 reprepare\n");
        exit(1);
    }
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_blog_autoload_name_value_stat4_current_source') {
        fwrite(STDERR, "expected current covering ORDER index\n");
        exit(1);
    }
    if (($output['coveringOrderPlan'] ?? null) !== true || ($output['tableLookupElided'] ?? null) !== true || ($output['tempSortElided'] ?? null) !== true) {
        fwrite(STDERR, "expected covering index to satisfy ORDER BY without table lookup or temp sort\n");
        exit(1);
    }

    echo "application-planner-stat4-order-covering-current-source self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
