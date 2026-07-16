<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $column): array => ['column' => $column];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$isNotNull = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexSql = "CREATE INDEX idx_wp_options_lower_autoload_partial_deferredLookup ON wp_options(lower(option_name), autoload) WHERE autoload = 'yes' AND option_name IS NOT NULL";
$prepared = [
    'name' => 'prepared-wp-options-copy',
    'schemaCookie' => 1560,
    'stat4Generation' => 20,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_partial_deferredLookup',
        'rootPage' => 15601,
        'estimatedRows' => 240,
        'stat4Samples' => [
            ['neq' => '5 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 'yes']],
            ['neq' => '3 1', 'nlt' => '5 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 'yes']],
        ],
        'coveringColumns' => ['autoload'],
        'sql' => $indexSql,
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'old-cache'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'old-forms'],
    ],
];
$current = [
    'name' => 'current-wp-options-after-plugin-import',
    'schemaCookie' => 1562,
    'stat4Generation' => 23,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_partial_deferredLookup',
        'rootPage' => 15631,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '4 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 'yes']],
            ['neq' => '2 1', 'nlt' => '4 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 'yes']],
            ['neq' => '1 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 'yes']],
        ],
        'coveringColumns' => ['autoload'],
        'sql' => $indexSql,
    ]],
    'rows' => [
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-a'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'cache-b'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms'],
        ['rowid' => 14, 'option_id' => 14, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo'],
        ['rowid' => 15, 'option_id' => 15, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'lazy'],
    ],
];

$lowerName = $expr('lower', 'option_name');
$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDeferredLookup(
    $prepared,
    $current,
    $and(
        $point($column('autoload'), 'yes'),
        $isNotNull('option_name'),
        $range($lowerName, '>=', 'plugin_cache'),
        $range($lowerName, '<', 'plugin_t'),
    ),
    [$lowerName],
    ['option_name', 'option_value', 'option_id'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-deferredLookup-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['selectedPlan']['partialRowids'] === [11, 12, 13, 14]);
    assert($plan['selectedPlan']['tableLookupRequired'] === true);
    echo "application-stat4-expression-partial-current-source-deferredLookup self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'rowids' => $plan['selectedPlan']['partialRowids'],
    'tableLookupRequired' => $plan['selectedPlan']['tableLookupRequired'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT) . PHP_EOL;
