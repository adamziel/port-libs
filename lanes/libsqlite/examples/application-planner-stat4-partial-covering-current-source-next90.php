<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4PartialCoveringCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-before-plugin-import',
    'schemaCookie' => 14,
    'stat4Generation' => 40,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_name_stat4_next90',
        'rootPage' => 9001,
        'estimatedRows' => 120,
        'stat4Samples' => [
            ['neq' => '1 3 3', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
            ['neq' => '1 8 8', 'nlt' => '3 3 3', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
            ['neq' => '1 13 13', 'nlt' => '11 11 11', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
            ['neq' => '1 11 11', 'nlt' => '24 24 24', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_name_stat4_next90 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-after-plugin-import';
$current['stat4Generation'] = 41;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 8 8', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '15 15 15', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '18 18 18', 'ndlt' => '5 5 5', 'sample' => [1, 'plugin_seo', 'yes']],
];

$plan = SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
    $prepared,
    $current,
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_cache'),
        $range('option_name', '<', 'plugin_seo')
    ),
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

$output = [
    'scenario' => 'application-planner-stat4-partial-covering-current-source-next90',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'partialPredicateImplied' => $plan['selectedPlan']['partialPredicateImplied'] ?? false,
    'covering' => $plan['selectedPlan']['covering'] ?? false,
    'preparedRows' => $plan['preparedSource']['estimatedRows'] ?? null,
    'currentRows' => $plan['currentSource']['estimatedRows'] ?? null,
    'rangeCurrentNext' => $plan['selectedPlan']['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied multisite wp_options plugin scans where a prepared partial covering index plan must be rebound to current sqlite_stat4 samples after plugin imports update option_name distribution.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedSource'] ?? null) !== 'current' || ($output['reprepareRequired'] ?? null) !== true) {
        fwrite(STDERR, "expected current-source reprepare\n");
        exit(1);
    }
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_blog_plugin_name_stat4_next90') {
        fwrite(STDERR, "expected STAT4 partial covering index\n");
        exit(1);
    }
    if (($output['currentRows'] ?? null) !== 17 || ($output['rangeCurrentNext']['upper']['current']['key'] ?? null) !== 'plugin_seo') {
        fwrite(STDERR, "expected current STAT4 range evidence\n");
        exit(1);
    }

    echo "application-planner-stat4-partial-covering-current-source-next90 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
