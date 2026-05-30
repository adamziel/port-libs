<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$like = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => '\\'];
$range = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-like-prefix-next175',
    'schemaCookie' => 1750,
    'stat4Generation' => 110,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old-old'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_like_prefix_next175',
        'rootPage' => 17501,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-like-prefix-next175';
$current['schemaCookie'] = 1759;
$current['stat4Generation'] = 127;
$current['indexes'][0]['rootPage'] = 17577;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['mu_plugin_loader', 5]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 10]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_search', 50]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 60]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['theme_mods_twenty', 70]],
];
$current['rows'] = [
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'mu_plugin_loader', 'option_value' => 'mu-current'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'search-current'],
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twenty', 'option_value' => 'theme-current'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeLikePrefixWindowFence(
    $prepared,
    $current,
    [
        $like('LOWER( option_name )', 'plugin\_%'),
        $eq('blog_id', 1),
        $eq('autoload', 'yes'),
        $notNull('option_name'),
        $range('lower(option_name)', '>=', 'plugin_'),
        $range('lower(option_name)', '<', 'plugin`'),
    ],
    ['option_name', 'option_value'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next175-ready');
    assert($plan['prefix'] === 'plugin_');
    assert($plan['matchedRowids'] === [10, 20, 50, 60]);
    assert($plan['stalePreparedRowidsBlockedByPrefixWindow'] === [30]);
    echo "application-sqlplanner-stat4-expression-partial-current-source-next175 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-sqlplanner-stat4-expression-partial-current-source-next175',
    'applicationUse' => 'Preview copied wp_options plugin scans after ANALYZE reparses a partial lower(option_name) index and admits the current STAT4 LIKE-prefix window while blocking stale prepared rows.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'prefix' => $plan['prefix'],
    'prefixUpperBound' => $plan['prefixUpperBound'],
    'stat4PrefixWindow' => $plan['stat4PrefixWindow'],
    'matchedRowids' => $plan['matchedRowids'],
    'stalePreparedRowidsBlockedByPrefixWindow' => $plan['stalePreparedRowidsBlockedByPrefixWindow'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
