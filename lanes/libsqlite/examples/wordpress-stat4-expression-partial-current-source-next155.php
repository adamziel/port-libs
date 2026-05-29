<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$notNull = static fn (array $left): array => ['operator' => 'IS NOT NULL', 'left' => $left];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-next155',
    'schemaCookie' => 1550,
    'stat4Generation' => 21,
    'rowGeneration' => 8,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_blog_autoload_partial_next155',
        'rootPage' => 15501,
        'estimatedRows' => 340,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [$lower],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_blog_autoload_partial_next155 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND blog_id = 1",
    ]],
    'rows' => [
        ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache', 'blog_id' => 1],
        ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms', 'blog_id' => 1],
    ],
];
$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-next155';
$current['schemaCookie'] = 1556;
$current['stat4Generation'] = 29;
$current['rowGeneration'] = 14;
$current['indexes'][0]['rootPage'] = 15566;
$current['indexes'][0]['estimatedRows'] = 24;
$current['indexes'][0]['sql'] = "CREATE INDEX idx_wp_options_lower_blog_autoload_partial_next155 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND blog_id = 1 AND option_value IS NOT NULL";
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 201]],
    ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 203]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 204]],
];
$current['rows'] = [
    ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'current-cache', 'blog_id' => 1],
    ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms', 'blog_id' => 1],
    ['rowid' => 201, 'option_id' => 201, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha', 'blog_id' => 1],
    ['rowid' => 202, 'option_id' => 202, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo', 'blog_id' => 1],
    ['rowid' => 203, 'option_id' => 203, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => null, 'blog_id' => 1],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext155(
    $prepared,
    $current,
    $and(
        $range($lower, '>=', 'plugin_'),
        $range($lower, '<', 'plugin_z'),
        $point($column('autoload'), 'yes'),
        $point($column('blog_id'), 1),
        $notNull($column('option_value')),
    ),
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next155-ready') {
        fwrite(STDERR, "expected ready STAT4 expression partial current-source plan\n");
        exit(1);
    }
    if (($plan['currentCoveringRowids'] ?? []) !== [201, 101, 102, 202]) {
        fwrite(STDERR, "unexpected current rowids\n");
        exit(1);
    }
    if (($plan['partialPredicateChanged'] ?? null) !== true) {
        fwrite(STDERR, "expected partial predicate drift\n");
        exit(1);
    }
    echo "wordpress-stat4-expression-partial-current-source-next155 self-test passed\n";
}

return [
    'scenario' => 'wordpress-stat4-expression-partial-current-source-next155',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'partialPredicateChanged' => $plan['partialPredicateChanged'],
    'currentCoveringRowids' => $plan['currentCoveringRowids'],
    'blockedPreparedRowids' => $plan['staleCoveringRejectedRowids'],
    'wordpressUse' => 'Copied wp_options plugin scans can reprepare stale STAT4 partial lower(option_name) plans when ANALYZE/DDL narrows the partial predicate, preserving covering index reads while filtering rows that no longer satisfy the current partial index.',
];
