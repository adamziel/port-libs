<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprEq = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];

$rows = [
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy'],
];

$source = static fn (string $name, int $cookie, int $stat4, int $rootPage, array $rows): array => [
    'name' => $name,
    'schemaCookie' => $cookie,
    'stat4Generation' => $stat4,
    'rows' => $rows,
    'indexes' => [[
        'name' => 'idx_wp_options_name_nocase_autoload_partial',
        'rootPage' => $rootPage,
        'expression' => 'option_name',
        'expressionColumn' => 'option_name',
        'collation' => 'NOCASE',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value'],
        'stat4Samples' => [
            ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Plugin_Cache', 2]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 4]],
        ],
    ]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRepreparedPartialExpressionIndex(
    $source('prepared-wp-options-copy', 410, 9, 4101, array_slice($rows, 0, 2)),
    $source('current-wp-options-copy-after-plugin-import', 411, 10, 4111, $rows),
    [
        $exprEq('option_name', 'plugin_cache'),
        $term('blog_id', '=', 1),
        $term('autoload', '=', 'yes'),
        $term('option_name', 'IS NOT NULL'),
    ],
    ['option_name', 'option_value'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-reprepare-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['selectedPlan']['matchedStat4Keys'] === ['Plugin_Cache']);
    assert($plan['selectedPlan']['matchedRowids'] === [2, 3]);
    assert(array_column(array_column($plan['matchedRows'], 'payload'), 'option_value') === ['warm', 'fresh']);
    echo "wordpress-sqlplanner-stat4-expression-partial-nocase-reprepare self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'collation' => $plan['selectedPlan']['collation'] ?? null,
    'matchedStat4Keys' => $plan['selectedPlan']['matchedStat4Keys'],
    'matchedRowids' => $plan['selectedPlan']['matchedRowids'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT) . PHP_EOL;
