<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-sample-window-next167',
    'schemaCookie' => 1670,
    'stat4Generation' => 81,
    'rows' => [
        ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache', 'updated_at' => 11],
        ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 22],
        ['rowid' => 33, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old', 'updated_at' => 33],
        ['rowid' => 44, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 44],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_window_next167',
        'rootPage' => 16701,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 33]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 44]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-sample-window-next167';
$current['schemaCookie'] = 1678;
$current['stat4Generation'] = 94;
$current['indexes'][0]['rootPage'] = 16788;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 55]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 44]],
];
$current['rows'] = [
    ['rowid' => 55, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 55],
    ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
    ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 22],
    ['rowid' => 44, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 44],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext167(
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
    assert($plan['status'] === 'stat4-expression-partial-current-source-next167-ready');
    assert($plan['stalePreparedRowidsBlockedBySampleFence'] === [33]);
    assert($plan['currentSourceRowidsAdmittedBySampleFence'] === [55]);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next167 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next167',
    'wordpressUse' => 'Preview copied wp_options plugin scans after ANALYZE changes a partial lower(option_name) STAT4 sample window, blocking stale prepared rowids while admitting the new current plugin row without falling back to a table scan.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'stalePreparedRowidsBlockedBySampleFence' => $plan['stalePreparedRowidsBlockedBySampleFence'],
    'currentSourceRowidsAdmittedBySampleFence' => $plan['currentSourceRowidsAdmittedBySampleFence'],
    'currentWindowKeys' => $plan['currentSampleWindow']['keys'],
    'tableLookupRequired' => $plan['tableLookupRequired'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
