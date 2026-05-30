<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$autoloadYes = $point($column('autoload'), 'yes');
$preparedPredicate = $and($range($lower, '>=', 'plugin_'), $range($lower, '<', 'plugin_z'), $autoloadYes);
$currentPredicate = $and($range($lower, '>=', 'plugin_c'), $range($lower, '<=', 'plugin_seo'), $autoloadYes);

$preparedSource = [
    'name' => 'prepared-application-stat4-expression-covering-range-next128',
    'schemaCookie' => 1280,
    'stat4Generation' => 52,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_covering_stat4_next128',
        'rootPage' => 12801,
        'estimatedRows' => 420,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 102]],
            ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 103]],
            ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 104]],
            ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_mail', 105]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_next128 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$currentSource = $preparedSource;
$currentSource['name'] = 'current-application-stat4-expression-covering-range-next128';
$currentSource['schemaCookie'] = 1286;
$currentSource['stat4Generation'] = 58;
$currentSource['indexes'][0]['rootPage'] = 12864;
$currentSource['indexes'][0]['stat4Samples'][] = ['neq' => '2 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 205]];

$rows = [
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
];

$plan = SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan::materializeCurrentSourceRange(
    $preparedSource,
    $currentSource,
    $preparedPredicate,
    $currentPredicate,
    $rows,
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'stat4-expression-covering-range-current-source-next128-ready');
    assert($plan['staleRangeRejectedRowids'] === [11]);
    assert($plan['currentMatchedRowids'] === [21, 31, 41, 51]);
    echo "application-stat4-expression-covering-range-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-stat4-expression-covering-range-current-source',
    'status' => $plan['status'],
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'currentRange' => $plan['currentRangeValues'],
    'rejectedPreparedRowids' => $plan['staleRangeRejectedRowids'],
    'matchedCurrentRowids' => $plan['currentMatchedRowids'],
    'tableLookupElided' => $plan['tableLookupElided'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
