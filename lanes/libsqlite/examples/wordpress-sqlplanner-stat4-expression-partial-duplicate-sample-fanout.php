<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-duplicate-fanout-next173',
    'schemaCookie' => 1730,
    'stat4Generation' => 91,
    'rows' => [
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache-a', 'updated_at' => 10],
        ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache-b', 'updated_at' => 11],
        ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old', 'updated_at' => 30],
        ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_duplicate_fanout_next173',
        'rootPage' => 17301,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload'],
        'stat4Samples' => [
            ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
            ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-duplicate-fanout-next173';
$current['schemaCookie'] = 1738;
$current['stat4Generation'] = 108;
$current['indexes'][0]['rootPage'] = 17388;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
];
$current['rows'] = [
    ['rowid' => 50, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'PLUGIN_CACHE', 'option_value' => 'fresh-cache-b', 'updated_at' => 12],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache-a', 'updated_at' => 14],
    ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeDuplicateSampleFanout(
    $prepared,
    $current,
    [
        $range('LOWER( option_name )', '>=', 'plugin_cache'),
        $range('lower(option_name)', '<', 'plugin_t'),
        $eq('autoload', 'yes'),
        $notNull('option_name'),
    ],
    ['option_name', 'option_value', 'updated_at'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next173-ready');
    assert($plan['duplicateStat4KeyBuckets'][0]['rowids'] === [10, 12]);
    assert($plan['stat4FanoutRowids'] === [10, 12, 20, 40, 50]);
    echo "wordpress-sqlplanner-stat4-expression-partial-duplicate-sample-fanout self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-duplicate-sample-fanout',
    'wordpressUse' => 'Preview copied wp_options plugin scans after ANALYZE keeps one STAT4 sample for duplicate lower(option_name) keys, expanding the current partial expression-index rowid fanout while blocking stale prepared rows.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'duplicateStat4KeyBuckets' => $plan['duplicateStat4KeyBuckets'],
    'stat4FanoutRowids' => $plan['stat4FanoutRowids'],
    'stalePreparedRowidsBlockedBySampleFence' => $plan['stalePreparedRowidsBlockedBySampleFence'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
