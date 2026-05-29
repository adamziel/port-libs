<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wp-options-expression-index',
    'schemaCookie' => 122,
    'stat4Generation' => 77,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_active_name',
        'rootPage' => 12301,
        'estimatedRows' => 80,
        'expressions' => [[
            'function' => 'lower',
            'column' => 'option_name',
            'collation' => 'NOCASE',
            'affinity' => 'TEXT',
        ]],
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'partialPredicate' => "lower(option_name) >= 'plugin_'",
        'sql' => "CREATE INDEX idx_wp_options_lower_active_name ON wp_options(lower(option_name) COLLATE NOCASE, autoload) WHERE lower(option_name) >= 'plugin_'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-expression-index';
$current['schemaCookie'] = 123;
$current['stat4Generation'] = 78;
$current['indexes'][0]['rootPage'] = 12310;
$current['indexes'][0]['estimatedRows'] = 5;

$plan = SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan::materializeNext123($prepared, $current, [
    'operator' => 'IN_SUBQUERY',
    'left' => ['function' => 'lower', 'column' => 'option_name', 'collation' => 'NOCASE', 'affinity' => 'TEXT'],
    'subquery' => [
        'sourceName' => 'wp_active_option_name_keys',
        'keyColumn' => 'expr_key',
        'collation' => 'NOCASE',
        'affinity' => 'TEXT',
        'rows' => [
            ['expr_key' => 'Plugin_Cache', 'blog_id' => 1],
            ['expr_key' => 'plugin_forms', 'blog_id' => 1],
            ['expr_key' => 'PLUGIN_CACHE', 'blog_id' => 1],
            ['expr_key' => 'plugin_security', 'blog_id' => 2],
        ],
        'correlatedOuterColumns' => ['blog_id', 'autoload'],
    ],
], ['option_id', 'option_name', 'autoload']);

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'indexName' => $plan['selectedPlan']['name'] ?? null,
    'values' => $plan['subquery']['values'],
    'tableLookupElided' => $plan['cursorTape']['tableLookupElided'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
