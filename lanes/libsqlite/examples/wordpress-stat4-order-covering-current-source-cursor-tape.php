<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4OrderCoveringCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-plugin-stat4-covering-order-cursor-tape',
    'schemaCookie' => 94,
    'stat4Generation' => 22,
    'coveringColumns' => ['autoload', 'blog_id', 'option_name', 'option_value'],
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_value_stat4_cursor_tape',
        'rootPage' => 9901,
        'estimatedRows' => 180,
        'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 140],
        'stat4Samples' => [
            ['neq' => '1 5 5 5', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
            ['neq' => '1 8 8 8', 'nlt' => '5 5 5 5', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
            ['neq' => '1 11 11 11', 'nlt' => '13 13 13 13', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_cursor_tape ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-plugin-stat4-covering-order-cursor-tape';
$current['schemaCookie'] = 99;
$current['stat4Generation'] = 24;
$current['indexes'][0]['rootPage'] = 9910;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 2 2 2', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
    ['neq' => '1 3 3 3', 'nlt' => '2 2 2 2', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_cache', 'a:2:{}']],
    ['neq' => '1 5 5 5', 'nlt' => '5 5 5 5', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_forms', 'a:3:{}']],
    ['neq' => '1 4 4 4', 'nlt' => '10 10 10 10', 'ndlt' => '3 3 3 3', 'sample' => [1, 'yes', 'plugin_security', 'a:4:{}']],
    ['neq' => '1 2 2 2', 'nlt' => '14 14 14 14', 'ndlt' => '4 4 4 4', 'sample' => [1, 'yes', 'plugin_slider', 'a:5:{}']],
];

$plan = SQLiteStat4OrderCoveringCurrentSourceNextPlan::materializeCoveringOrderCursorTape(
    $prepared,
    $current,
    $and(
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'plugin_z'),
    ),
    [['column' => 'option_name'], ['column' => 'option_value']],
    ['option_name', 'option_value', 'autoload'],
);

$output = [
    'scenario' => 'wordpress-stat4-order-covering-current-source-cursor-tape',
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['cursorTape']['indexName'],
    'rootPage' => $plan['cursorTape']['rootPage'],
    'status' => $plan['status'],
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'seekOpcode' => $plan['cursorTape']['seekOpcode'],
    'stopOpcode' => $plan['cursorTape']['stopOpcode'],
    'nextOpcode' => $plan['cursorTape']['nextOpcode'],
    'deferredSeekOpcode' => $plan['cursorTape']['deferredSeekOpcode'],
    'sorterOpen' => $plan['cursorTape']['sorterOpen'],
    'wordpressUse' => 'Preview copied wp_options plugin autoload SELECT planning after ANALYZE refresh: STAT4 current-source reprepare keeps ORDER BY on a covering index cursor, avoiding table rowid lookups and temp sorting without ext/sqlite.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedSource'] ?? null) !== 'current' || ($output['status'] ?? null) !== 'covering-order-current-source-ready') {
        fwrite(STDERR, "expected current-source cursor tape to be ready\n");
        exit(1);
    }
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_blog_autoload_name_value_stat4_cursor_tape') {
        fwrite(STDERR, "expected current covering ORDER cursor index\n");
        exit(1);
    }
    if (($output['matchedKeys'] ?? []) !== ['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_slider']) {
        fwrite(STDERR, "expected current STAT4 cursor tape matched keys\n");
        exit(1);
    }

    echo "wordpress-stat4-order-covering-current-source-cursor-tape self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
