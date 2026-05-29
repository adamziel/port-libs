<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprEq = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];
$exprGt = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '>', 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-unsampled-next171',
    'schemaCookie' => 1710,
    'stat4Generation' => 40,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-stale'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old-stale'],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-stale'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_unsampled_next171',
        'rootPage' => 17101,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-unsampled-next171';
$current['schemaCookie'] = 1717;
$current['stat4Generation'] = 49;
$current['indexes'][0]['rootPage'] = 17177;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
    ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 50]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
];
$current['rows'] = [
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-current'],
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Search', 'option_value' => 'search-current'],
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext171(
    $prepared,
    $current,
    [
        $exprEq('LOWER( option_name )', 'plugin_search'),
        $eq('blog_id', 1),
        $eq('autoload', 'yes'),
        $notNull('option_name'),
        $exprGt('lower(option_name)', 'plugin_'),
    ],
    ['option_name', 'option_value'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next171-ready');
    assert($plan['matchedRowids'] === [60]);
    assert($plan['stat4Bracket']['left']['key'] === 'plugin_forms');
    assert($plan['stat4Bracket']['right']['key'] === 'plugin_security');
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next171 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next171',
    'wordpressUse' => 'Preview copied wp_options plugin scans after ANALYZE when the requested lower(option_name) key is not itself a sqlite_stat4 sample but is bracketed by current samples.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'unsampledEqualityKey' => $plan['unsampledEqualityKey'],
    'stat4Bracket' => [
        'kind' => $plan['stat4Bracket']['kind'] ?? null,
        'left' => $plan['stat4Bracket']['left']['key'] ?? null,
        'right' => $plan['stat4Bracket']['right']['key'] ?? null,
    ],
    'matchedRowids' => $plan['matchedRowids'],
    'tableLookupRequired' => $plan['tableLookupRequired'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
