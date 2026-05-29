<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$preparedPredicate = $and($range($lower, '>=', 'plugin_'), $range($lower, '<=', 'plugin_z'), $point($column('autoload'), 'yes'));
$currentPredicate = $and($range($lower, '>', 'plugin_beta'), $range($lower, '<=', 'plugin_seo'), $point($column('autoload'), 'yes'));

$preparedSource = [
    'name' => 'prepared-wordpress-covering-expression-range-descending',
    'schemaCookie' => 1340,
    'stat4Generation' => 41,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_covering_descending',
        'rootPage' => 13401,
        'estimatedRows' => 512,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 102]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 103]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 104]],
            ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_mail', 105]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin_seo', 106]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_desc_covering_descending ON wp_options(lower(option_name) DESC, option_id DESC, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$currentSource = $preparedSource;
$currentSource['name'] = 'current-wordpress-covering-expression-range-descending';
$currentSource['schemaCookie'] = 1348;
$currentSource['stat4Generation'] = 49;
$currentSource['indexes'][0]['rootPage'] = 13488;

$rows = [
    ['rowid' => 10, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 10, 'blog_id' => 1],
    ['rowid' => 20, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta-enabled', 'option_id' => 20, 'blog_id' => 1],
    ['rowid' => 30, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 30, 'blog_id' => 1],
    ['rowid' => 35, 'option_name' => 'Plugin_Cache_Extra', 'autoload' => 'yes', 'option_value' => 'cache-extra', 'option_id' => 35, 'blog_id' => 2],
    ['rowid' => 40, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 40, 'blog_id' => 1],
    ['rowid' => 50, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 50, 'blog_id' => 3],
    ['rowid' => 60, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 60, 'blog_id' => 1],
];

$plan = SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan::materializeDescendingCurrentRange(
    $preparedSource,
    $currentSource,
    $preparedPredicate,
    $currentPredicate,
    $rows,
    [['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC'], ['column' => 'option_id', 'direction' => 'DESC']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'covering-expression-range-current-source-descending-ready');
    assert($plan['currentSourceNextRowids'] === [60, 50, 40, 35, 30]);
    assert($plan['staleRangeRejectedRowids'] === [10, 20]);
    assert($plan['cursorTape']['nextOpcode'] === 'Prev');
    echo "wordpress-covering-expression-range-current-source-descending self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-covering-expression-range-current-source-descending',
    'status' => $plan['status'],
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'rangeDirection' => $plan['rangeDirection'],
    'seekOpcode' => $plan['rangeSeekOpcode'],
    'stopOpcode' => $plan['rangeStopOpcode'],
    'currentSourceNextRowids' => $plan['currentSourceNextRowids'],
    'staleRangeRejectedRowids' => $plan['staleRangeRejectedRowids'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'wordpressUse' => 'Copied wp_options plugin admin screens can stream lower(option_name) DESC from the current covering expression index after ANALYZE/schema changes, rejecting stale prepared bounds without table b-tree seeks.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
