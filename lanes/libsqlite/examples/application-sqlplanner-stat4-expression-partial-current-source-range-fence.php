<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lower = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => $lower, 'right' => 'plugin_'],
        ['operator' => '<', 'left' => $lower, 'right' => 'plugin_z'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$preparedSource = [
    'name' => 'prepared-wp-options-expression-partial-rangeFence',
    'schemaCookie' => 1580,
    'stat4Generation' => 90,
    'rowGeneration' => 20,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_stat4_rangeFence',
        'rootPage' => 15801,
        'estimatedRows' => 420,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_partial_stat4_rangeFence ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ]],
];
$currentSource = $preparedSource;
$currentSource['name'] = 'current-wp-options-expression-partial-rangeFence';
$currentSource['schemaCookie'] = 1586;
$currentSource['stat4Generation'] = 99;
$currentSource['rowGeneration'] = 27;
$currentSource['indexes'][0]['rootPage'] = 15866;
$currentSource['indexes'][0]['estimatedRows'] = 96;
$currentSource['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
    ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 21]],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 31]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 41]],
    ['neq' => '3 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 51]],
    ['neq' => '1 1', 'nlt' => '8 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 71]],
];
$preparedRows = [
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-old', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_deleted', 'autoload' => 'yes', 'option_value' => 'deleted', 'option_id' => 81, 'blog_id' => 1],
];
$currentRows = [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-new', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceRangeFence(
    $preparedSource,
    $currentSource,
    $predicate,
    $preparedRows,
    $currentRows,
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

echo json_encode([
    'scenario' => 'application-sqlplanner-stat4-expression-partial-current-source-rangeFence',
    'applicationUse' => 'Preview copied wp_options plugin-option scans using a partial lower(option_name) STAT4 expression index after ANALYZE/source changes, blocking stale prepared rows while reading only the current covering range window.',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'rangeWindowRowids' => $plan['rangeWindowRowids'],
    'rangeWindowKeys' => $plan['rangeWindowKeys'],
    'stalePreparedRowidsBlocked' => $plan['stalePreparedRowidsBlockedByRangeFence'],
    'admittedCurrentRowids' => $plan['currentSourceRowidsAdmittedByRangeFence'],
    'refreshedCurrentRowids' => $plan['currentSourceRowidsRefreshedByRangeFence'],
    'tableLookupElided' => $plan['cursorTape']['tableLookupElidedForRangeWindow'],
    'lowerFenceKey' => $plan['lowerFenceKey'],
    'upperFenceKey' => $plan['upperFenceKey'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
