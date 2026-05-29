<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $expression, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => $expression], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-wp-options-next172',
    'schemaCookie' => 1720,
    'stat4Generation' => 50,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_partial_next172_old',
        'rootPage' => 17201,
        'expression' => 'lower(option_name)',
        'partialPredicate' => $or($eq('autoload', 'yes'), $eq('blog_id', 0)),
        'stat4Samples' => [
            ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 2]],
            ['neq' => '2 2', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 3]],
        ],
    ]],
    'rows' => [
        ['rowid' => 2, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 3, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'blog_id' => 1],
    ],
];

$current = $prepared;
$current['name'] = 'current-wp-options-next172';
$current['schemaCookie'] = 1724;
$current['stat4Generation'] = 57;
$current['indexes'][0]['name'] = 'idx_wp_options_lower_plugin_partial_next172_current';
$current['indexes'][0]['rootPage'] = 17211;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 12]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 13]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 15]],
    ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 3', 'sample' => ['plugin_security', 16]],
];
$current['rows'] = [
    ['rowid' => 12, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'blog_id' => 1],
    ['rowid' => 13, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'blog_id' => 1],
    ['rowid' => 14, 'option_name' => 'Plugin_Cache_2', 'autoload' => 'yes', 'blog_id' => 1],
    ['rowid' => 15, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'blog_id' => 1],
    ['rowid' => 16, 'option_name' => 'Plugin_Security', 'autoload' => 'yes', 'blog_id' => 1],
    ['rowid' => 18, 'option_name' => 'Plugin_Trash', 'autoload' => 'no', 'blog_id' => 1],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext172(
    $prepared,
    $current,
    $and($eq('autoload', 'yes'), $range('lower(option_name)', '>=', 'plugin_'), $range('lower(option_name)', '<', 'plugin_t')),
);

if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next172-ready') {
    fwrite(STDERR, "STAT4 expression partial current-source plan was not ready\n");
    exit(1);
}
if (($plan['selectedSource'] ?? null) !== 'current' || ($plan['selectedPlan']['matchedRowids'] ?? []) !== [12, 13, 14, 15, 16]) {
    fwrite(STDERR, "STAT4 expression partial current-source plan did not select current plugin rows\n");
    exit(1);
}

if (($_SERVER['argv'][1] ?? '') === '--self-test') {
    echo "wordpress-planner-stat4-expression-partial-current-source-next172 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-planner-stat4-expression-partial-current-source-next172',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'indexName' => $plan['selectedPlan']['indexName'],
    'matchedRowids' => $plan['selectedPlan']['matchedRowids'],
    'matchedStat4Keys' => $plan['selectedPlan']['matchedStat4Keys'],
    'estimatedRows' => $plan['selectedPlan']['estimatedRows'],
    'wordpressUse' => 'Copied wp_options plugin screens can reprepare stale partial expression-index STAT4 samples after ANALYZE/schema changes, keeping plugin autoload scans on the current-source index without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
