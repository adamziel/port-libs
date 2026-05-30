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
require_once __DIR__ . '/../src/SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$preparedPredicate = $and($range($lower, '>=', 'plugin_'), $range($lower, '<=', 'plugin_z'), $point($column('autoload'), 'yes'));
$currentPredicate = $and($range($lower, '>', 'plugin_beta'), $range($lower, '<=', 'plugin_seo'), $point($column('autoload'), 'yes'));

$preparedSource = [
    'name' => 'prepared-wp-expression-covering-range-next146',
    'schemaCookie' => 1460,
    'stat4Generation' => 70,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_covering_next146',
        'rootPage' => 14601,
        'estimatedRows' => 720,
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
        'sql' => "CREATE INDEX idx_wp_options_lower_desc_covering_next146 ON wp_options(lower(option_name) DESC, option_id DESC, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$currentSource = $preparedSource;
$currentSource['name'] = 'current-wp-expression-covering-range-next146';
$currentSource['schemaCookie'] = 1464;
$currentSource['stat4Generation'] = 76;
$currentSource['indexes'][0]['rootPage'] = 14644;

$rows = [
    ['rowid' => 10, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 10, 'blog_id' => 1],
    ['rowid' => 20, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta-enabled', 'option_id' => 20, 'blog_id' => 1],
    ['rowid' => 30, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 30, 'blog_id' => 1],
    ['rowid' => 35, 'option_name' => 'Plugin_Cache_Extra', 'autoload' => 'yes', 'option_value' => 'cache-extra', 'option_id' => 35, 'blog_id' => 2],
    ['rowid' => 40, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 40, 'blog_id' => 1],
    ['rowid' => 50, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 50, 'blog_id' => 3],
    ['rowid' => 60, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 60, 'blog_id' => 1],
];
$nextSource = $currentSource + ['rows' => $rows];

$plan = SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan::materialize(
    $preparedSource,
    $currentSource,
    $nextSource,
    $preparedPredicate,
    $currentPredicate,
    $rows,
    [['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC'], ['column' => 'option_id', 'direction' => 'DESC']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'expression-covering-range-current-source-next146-ready');
    assert($plan['coveringRangeRowids'] === [60, 50, 40, 35, 30]);
    assert($plan['nextSourceAdmitted'] === true);
    assert($plan['cursorTape']['program'][0]['opcode'] === 'FenceCurrentSource');
    echo "application-expression-covering-range-current-source-next146 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-expression-covering-range-current-source-next146',
    'status' => $plan['status'],
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'rangeDirection' => $plan['rangeDirection'],
    'currentSourceNextRowids' => $plan['coveringRangeRowids'],
    'staleRangeRejectedRowids' => $plan['staleRangeRejectedRowids'],
    'nextSourceAdmitted' => $plan['nextSourceAdmitted'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'applicationUse' => 'Copied wp_options plugin screens can keep a lower(option_name) covering expression range cursor after ANALYZE/schema refresh only when the current covering payload matches the next source fence, avoiding stale prepared bounds and table b-tree lookups.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
