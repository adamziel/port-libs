<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4PartialCoveringCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-plugin-plan-next100',
    'schemaCookie' => 77,
    'stat4Generation' => 14,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [[
        'name' => 'idx_wp_options_plugin_cover_next100',
        'rootPage' => 410,
        'estimatedRows' => 100,
        'stat4Samples' => [
            ['neq' => '1 4 4', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
            ['neq' => '1 8 8', 'nlt' => '4 4 4', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_forms', 'yes']],
            ['neq' => '1 6 6', 'nlt' => '12 12 12', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_security', 'yes']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_plugin_cover_next100 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-plugin-plan-next100';
$current['indexes'][0]['rootPage'] = 411;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '10 10 10', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_seo', 'yes']],
];

$plan = SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
    $prepared,
    $current,
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_cache'),
        $range('option_name', '<', 'plugin_seo'),
    ),
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

$output = [
    'scenario' => 'application-planner-expression-partial-covering-current-source-next100',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'indexSignatureChanged' => $plan['indexSignatureChanged'],
    'schemaCookieChanged' => $plan['schemaCookieChanged'],
    'stat4GenerationChanged' => $plan['stat4GenerationChanged'],
    'projectionChanged' => $plan['projectionChanged'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'covering' => $plan['selectedPlan']['covering'] ?? false,
    'partialPredicateImplied' => $plan['selectedPlan']['partialPredicateImplied'] ?? false,
    'preparedRows' => $plan['preparedSource']['estimatedRows'] ?? null,
    'currentRows' => $plan['currentSource']['estimatedRows'] ?? null,
    'rangeCurrentNext' => $plan['selectedPlan']['stat4RangeCurrentNext'] ?? null,
    'applicationUse' => 'Preview copied wp_options plugin scans where schema/stat4 counters are stable but the current partial covering index root/stat4 payload changed, forcing native PHP plans to reprepare from current source instead of reusing stale prepared coverage.',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($output['selectedSource'] ?? null) !== 'current' || ($output['indexSignatureChanged'] ?? null) !== true) {
        fwrite(STDERR, "expected current-source reprepare from index signature change\n");
        exit(1);
    }
    if (($output['schemaCookieChanged'] ?? null) !== false || ($output['stat4GenerationChanged'] ?? null) !== false || ($output['projectionChanged'] ?? null) !== false) {
        fwrite(STDERR, "expected only index signature to invalidate the prepared plan\n");
        exit(1);
    }
    if (($output['selectedRootPage'] ?? null) !== 411 || ($output['currentRows'] ?? null) !== 10) {
        fwrite(STDERR, "expected current partial covering root and estimate\n");
        exit(1);
    }

    echo "application-planner-expression-partial-covering-current-source-next100 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
