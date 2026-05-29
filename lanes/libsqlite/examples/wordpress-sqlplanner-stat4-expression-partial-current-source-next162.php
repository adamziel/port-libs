<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-next162',
    'schemaCookie' => 1620,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache-a'],
        ['rowid' => 11, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network-cache'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_next162',
        'rootPage' => 16201,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 10]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-next162';
$current['schemaCookie'] = 1624;
$current['stat4Generation'] = 37;
$current['rows'] = [
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'current-cache-a'],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'current-cache-b'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_CACHE', 'option_value' => 'current-cache-c'],
    ['rowid' => 23, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network-cache'],
];
$current['indexes'][0]['rootPage'] = 16241;
$current['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '3', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 20]],
    ['neq' => '1', 'nlt' => '3', 'ndlt' => '1', 'sample' => ['siteurl', 30]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext162(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '=', 'right' => 'plugin_cache'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value', 'blog_id'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next162-ready');
    assert($plan['partialPredicateChanged'] === true);
    assert($plan['exactEqualityRowids'] === [20, 21, 22]);
    assert($plan['exactEqualityBuckets'][0]['rowCount'] === 3);
    assert(in_array(23, $plan['selectedPlan']['matchedRowids'], true) === false);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next162 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next162',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'partialPredicateChanged' => $plan['partialPredicateChanged'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'exactEqualityRowids' => $plan['exactEqualityRowids'],
    'stalePreparedRowidsBlockedByPartialDelta' => $plan['stalePreparedRowidsBlockedByPartialDelta'],
    'wordpressUse' => 'Copied wp_options multisite imports can reprepare a stale partial expression index after ANALYZE and partial WHERE changes, then scan only the current blog equality bucket without leaking network option rows.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
