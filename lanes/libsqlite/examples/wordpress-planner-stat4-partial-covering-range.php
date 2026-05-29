<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows = [
    ['rowid' => 1, 'blog_id' => 1, 'kind' => 'core', 'autoload' => 'yes', 'option_name' => 'home', 'option_value' => 'https://example.test'],
    ['rowid' => 2, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'warm'],
    ['rowid' => 3, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_editor', 'option_value' => 'blocks'],
    ['rowid' => 4, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 5, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield'],
    ['rowid' => 6, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy'],
];

$prepared = [
    'name' => 'prepared-before-current-source-next135',
    'schemaCookie' => 1350,
    'stat4Generation' => 70,
    'coveringColumns' => ['autoload', 'option_value', 'rowid'],
    'rows' => array_slice($rows, 0, 4),
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_stat4_cover_next135',
        'rootPage' => 13501,
        'estimatedRows' => 140,
        'stat4Samples' => [
            ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
            ['neq' => '1 3 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_forms', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next135 ON wp_options(blog_id, option_name, autoload, option_value, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ]],
];
$current = $prepared;
$current['name'] = 'current-after-plugin-import-next135';
$current['schemaCookie'] = 1351;
$current['stat4Generation'] = 71;
$current['rootPage'] = 13511;
$current['rows'] = $rows;
$current['indexes'][0]['rootPage'] = 13511;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '4 4 4', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
];

$plan = SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan::materializePartialCoveringRange(
    $prepared,
    $current,
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_cache'),
        $range('option_name', '<=', 'plugin_security')
    ),
    [['column' => 'option_name']],
    ['autoload', 'option_value', 'rowid'],
);

$output = [
    'scenario' => 'wordpress-planner-stat4-partial-covering-range',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'coveredRowids' => array_column($plan['coveredRows'], 'rowid'),
    'tableLookupElided' => $plan['tableLookupElided'],
    'rowStreamSignature' => $plan['currentSourceFence']['rowStreamSignature'],
    'wordpressUse' => 'Preview copied wp_options imports where a stale prepared partial covering STAT4 scan must rebind to current sqlite_stat4 samples and emit the current index row stream without table lookups.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedSource'] ?? null) !== 'current' || ($output['reprepareRequired'] ?? null) !== true) {
        fwrite(STDERR, "expected current-source reprepare\n");
        exit(1);
    }
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_blog_plugin_stat4_cover_next135') {
        fwrite(STDERR, "expected STAT4 partial covering index\n");
        exit(1);
    }
    if (($output['coveredRowids'] ?? []) !== [2, 3, 4, 5] || ($output['tableLookupElided'] ?? null) !== true) {
        fwrite(STDERR, "expected current covering row stream\n");
        exit(1);
    }

    echo "wordpress-planner-stat4-partial-covering-range self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
