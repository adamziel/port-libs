<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name',
        'rootPage' => 41,
        'estimatedRows' => 8000,
        'sql' => 'CREATE INDEX idx_wp_options_lower_name ON wp_options(lower(option_name))',
    ],
    [
        'name' => 'idx_wp_options_lower_name_covering',
        'rootPage' => 42,
        'estimatedRows' => 8000,
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_covering ON wp_options(lower(option_name), autoload, option_value)',
    ],
];

$predicate = [
    'operator' => '=',
    'left' => ['function' => 'lower', 'column' => 'option_name'],
    'right' => 'siteurl',
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes,
    $predicate,
    [],
    ['autoload', 'option_value'],
    [['function' => 'lower', 'column' => 'option_name']]
);

echo json_encode([
    'scenario' => 'application-select-expression-index-covering',
    'selectedIndex' => $plan['name'] ?? null,
    'rootPage' => $plan['rootPage'] ?? null,
    'covering' => (bool) ($plan['covering'] ?? false),
    'coveringExpressions' => $plan['coveringExpressions'] ?? [],
    'trailingColumns' => $plan['trailingColumns'] ?? [],
    'applicationUse' => 'Preview copied wp_options SELECT lower(option_name), autoload, option_value lookups where the expression index supplies the lower(option_name) result and trailing index columns avoid table-page reads without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
