<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan;

$lower = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'IN',
    'left' => $lower,
    'values' => ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'],
];
$prepared = [
    'name' => 'prepared-wp-options-stat4-covering-expression-in-',
    'schemaCookie' => 1260,
    'stat4Generation' => 42,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_in_covering_stat4_',
        'rootPage' => 12601,
        'estimatedRows' => 480,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [$lower],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
            ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 202]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 203]],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_lower_in_covering_stat4_ ON wp_options(lower(option_name), option_id, option_value, blog_id, autoload)',
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-stat4-covering-expression-in-';
$current['schemaCookie'] = 1264;
$current['stat4Generation'] = 45;
$current['indexes'][0]['rootPage'] = 12644;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 401]],
    ['neq' => '3 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 402]],
    ['neq' => '1 1', 'nlt' => '5 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 403]],
    ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 404]],
];
$rows = [
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 71, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta', 'option_id' => 71, 'blog_id' => 1],
];

$plan = SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $predicate,
    $rows,
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'stat4-covering-expression-in-current-source-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['tableLookupElided'] === true);
    assert($plan['cursorTape']['seekKeys'] === ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo']);
    assert($plan['cursorTape']['matchedKeys'] === ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo']);
    echo "application-stat4-covering-expression-in-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-stat4-covering-expression-in-current-source',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'seekKeys' => $plan['cursorTape']['seekKeys'],
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'applicationUse' => 'Preview copied wp_options plugin option-name IN scans after ANALYZE refresh: stale prepared expression-index plans reprepare to current STAT4 samples and read option payload columns from the covering index cursor.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
