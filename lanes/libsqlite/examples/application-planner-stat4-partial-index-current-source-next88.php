<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePartialIndexOrderCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = [
    [
        'name' => 'idx_wp_options_blog_plugin_name_stat4_next88',
        'rootPage' => 8801,
        'estimatedRows' => 80,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
            ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
            ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_editor', 'yes']],
            ['neq' => '1 8 8', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_forms', 'yes']],
            ['neq' => '1 3 3', 'nlt' => '15 15 15', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_security', 'yes']],
            ['neq' => '1 5 5', 'nlt' => '18 18 18', 'ndlt' => '5 5 5', 'sample' => [1, 'plugin_seo', 'yes']],
            ['neq' => '1 2 2', 'nlt' => '23 23 23', 'ndlt' => '6 6 6', 'sample' => [1, 'theme_mods', 'yes']],
            ['neq' => '1 1 1', 'nlt' => '25 25 25', 'ndlt' => '7 7 7', 'sample' => [2, 'plugin_cache', 'yes']],
            ['neq' => '1 1 1', 'nlt' => '26 26 26', 'ndlt' => '8 8 8', 'sample' => [2, 'plugin_forms', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_name_stat4_next88 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
];

$plan = SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes,
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
    'scenario' => 'application-planner-stat4-partial-index-current-source-next88',
    'selectedIndex' => $plan['name'] ?? null,
    'partialPredicateImplied' => (bool) ($plan['partialPredicateImplied'] ?? false),
    'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'currentSourceColumn' => $plan['stat4CurrentSourceColumn'] ?? null,
    'currentSourceOffset' => $plan['stat4CurrentSourceOffset'] ?? null,
    'effectiveRange' => $plan['rangeConstraint']['values'] ?? null,
    'rangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied multisite wp_options plugin-option scans where a partial index predicate is proved and sqlite_stat4 samples at the current option_name source tighten import/query planning without ext/sqlite.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedIndex'] ?? null) !== 'idx_wp_options_blog_plugin_name_stat4_next88') {
        fwrite(STDERR, "expected STAT4 partial index\n");
        exit(1);
    }
    if (($output['partialPredicateImplied'] ?? null) !== true || ($output['stat4Used'] ?? null) !== true) {
        fwrite(STDERR, "expected proved partial predicate with STAT4 estimate\n");
        exit(1);
    }
    if (($output['estimatedRows'] ?? null) !== 17 || ($output['effectiveRange']['upper'] ?? null) !== 'plugin_seo') {
        fwrite(STDERR, "expected tight current-source STAT4 range\n");
        exit(1);
    }

    echo "application-planner-stat4-partial-index-current-source-next88 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
