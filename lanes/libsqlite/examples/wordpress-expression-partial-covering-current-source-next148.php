<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteExpressionPartialCoveringCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wp-options-next148',
    'schemaCookie' => 1480,
    'stat4Generation' => 8,
    'rows' => [
        ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_cover_next148',
        'rootPage' => 14801,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['blog_id', 'option_name', 'option_value', 'updated_at', 'rowid'],
        'estimatedRows' => 20,
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-next148';
$current['schemaCookie'] = 1484;
$current['stat4Generation'] = 12;
$prepared['rows'][0]['blog_id'] = 1;
$current['rows'][0]['blog_id'] = 1;
$current['rows'][] = ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50];
$current['rows'][] = ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 60];
$current['indexes'][0]['rootPage'] = 14841;
$current['indexes'][0]['estimatedRows'] = 10;

$plan = SQLiteExpressionPartialCoveringCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '=', 'right' => 'plugin_cache'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
    ['option_name', 'option_value', 'updated_at', 'rowid'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'expression-partial-covering-current-source-next148-ready');
    assert($plan['selectedSource'] === 'current');
    assert(array_column($plan['coveredRows'], 'rowid') === [2, 3]);
    assert($plan['tableLookupElided'] === true);
    echo "wordpress-expression-partial-covering-current-source-next148 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-expression-partial-covering-current-source-next148',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'coveredRowids' => array_column($plan['coveredRows'], 'rowid'),
    'tableLookupElided' => $plan['tableLookupElided'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
