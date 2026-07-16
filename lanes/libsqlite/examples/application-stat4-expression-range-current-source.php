<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionRangeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => ['expression' => 'substr(option_name, 1, 12)'], 'right' => 'plugin_cache'],
        ['operator' => '<', 'left' => ['expression' => 'substr(option_name, 1, 12)'], 'right' => 'plugin_forms'],
    ],
];

$prepared = [
    'name' => 'prepared-before-plugin-analyze',
    'schemaCookie' => 104,
    'stat4Generation' => 21,
    'coveringColumns' => ['option_name', 'option_value', 'autoload'],
    'indexes' => [[
        'name' => 'idx_wp_options_expr_plugin_prefix_next104',
        'rootPage' => 10401,
        'expression' => 'substr(option_name, 1, 12)',
        'estimatedRows' => 320,
        'coveringColumns' => ['option_name', 'option_value', 'autoload'],
        'stat4Samples' => [
            ['neq' => '12 12', 'nlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
            ['neq' => '48 48', 'nlt' => '12 12', 'sample' => ['plugin_cache', 21]],
            ['neq' => '72 72', 'nlt' => '60 60', 'sample' => ['plugin_forms', 31]],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_expr_plugin_prefix_next104 ON wp_options(substr(option_name, 1, 12), option_value)',
    ]],
];

$current = $prepared;
$current['name'] = 'current-after-plugin-import-analyze';
$current['schemaCookie'] = 105;
$current['stat4Generation'] = 22;
$current['indexes'][0]['rootPage'] = 10410;
$current['indexes'][0]['estimatedRows'] = 220;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '3 3', 'nlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
    ['neq' => '7 7', 'nlt' => '3 3', 'sample' => ['plugin_cache', 102]],
    ['neq' => '9 9', 'nlt' => '10 10', 'sample' => ['plugin_commerce', 103]],
    ['neq' => '6 6', 'nlt' => '19 19', 'sample' => ['plugin_editor', 104]],
    ['neq' => '5 5', 'nlt' => '25 25', 'sample' => ['plugin_forms', 105]],
];

$plan = SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($prepared, $current, $predicate, ['option_name', 'option_value', 'autoload']);

echo json_encode([
    'scenario' => 'application-stat4-expression-range-current-source-next104',
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'],
    'rootPage' => $plan['selectedPlan']['rootPage'],
    'estimatedRows' => $plan['selectedPlan']['estimatedRows'],
    'range' => [
        'lower' => $plan['selectedPlan']['lowerBound'],
        'upper' => $plan['selectedPlan']['upperBound'],
        'lowerNextKey' => $plan['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key'],
        'upperCurrentKey' => $plan['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key'],
    ],
    'reprepareRequired' => $plan['reprepareRequired'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
