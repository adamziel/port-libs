<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial',
    'schemaCookie' => 1630,
    'stat4Generation' => 7,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_updated_old',
        'rootPage' => 16301,
        'expression' => 'lower(option_name)',
        'expressionColumn' => 'option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'baseCost' => 4,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'sample' => ['siteurl', 100, 1]],
            ['neq' => '2 1 1', 'nlt' => '1 1 1', 'sample' => ['siteurl', 200, 2]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial';
$current['schemaCookie'] = 1634;
$current['stat4Generation'] = 11;
$current['indexes'][0]['name'] = 'idx_wp_options_lower_autoload_updated';
$current['indexes'][0]['rootPage'] = 16341;
$current['indexes'][0]['baseCost'] = 2;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'sample' => ['home', 130, 20]],
    ['neq' => '1 1 1', 'nlt' => '1 1 1', 'sample' => ['siteurl', 90, 21]],
    ['neq' => '2 1 1', 'nlt' => '2 2 2', 'sample' => ['siteurl', 150, 22]],
    ['neq' => '3 1 1', 'nlt' => '4 4 4', 'sample' => ['siteurl', 260, 23]],
    ['neq' => '1 1 1', 'nlt' => '7 7 7', 'sample' => ['siteurl', 420, 24]],
];

$terms = [
    ['left' => ['expression' => 'LOWER( option_name )'], 'operator' => '=', 'right' => 'siteurl'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'updated_at'], 'operator' => 'BETWEEN', 'lower' => 100, 'upper' => 300],
];

$plan = SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize($prepared, $current, $terms);

echo json_encode([
    'scenario' => 'application-stat4-expression-partial-current-source',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'matchedRowids' => $plan['selectedPlan']['matchedRowids'] ?? [],
    'estimatedRows' => $plan['selectedPlan']['estimatedRows'] ?? null,
    'seekOpcode' => $plan['cursorTape']['seekOpcode'] ?? null,
    'stopOpcode' => $plan['cursorTape']['stopOpcode'] ?? null,
    'applicationUse' => 'Preview a copied wp_options autoload lookup after ANALYZE/stat4 refresh so stale prepared partial-expression plans reprepare to current-source sample selectivity before native row decoding.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
