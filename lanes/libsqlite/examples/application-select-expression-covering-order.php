<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_autoload_order',
        'rootPage' => 81,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_wp_options_lower_autoload_order ON wp_options(lower(option_name), autoload, option_id DESC, option_value)',
    ],
    [
        'name' => 'idx_wp_options_lower_plain',
        'rootPage' => 82,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_wp_options_lower_plain ON wp_options(lower(option_name))',
    ],
];

$predicate = ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'siteurl'];
$ranked = SQLiteSelectExpressionIndexPlan::rankedPlans(
    $indexes,
    $predicate,
    [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']],
    ['autoload', 'option_id', 'option_value'],
);
$selected = $ranked[0] ?? null;

echo json_encode([
    'scenario' => 'application-select-expression-covering-order',
    'selectedIndex' => $selected['name'] ?? null,
    'rootPage' => $selected['rootPage'] ?? null,
    'covering' => (bool) ($selected['covering'] ?? false),
    'orderBySatisfied' => (bool) ($selected['orderBySatisfied'] ?? false),
    'trailingColumns' => $selected['trailingColumns'] ?? [],
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'estimatedCost' => $selected['estimatedCost'] ?? null,
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'] ?? null,
            'covering' => (bool) ($plan['covering'] ?? false),
            'orderBySatisfied' => (bool) ($plan['orderBySatisfied'] ?? false),
            'estimatedCost' => $plan['estimatedCost'] ?? null,
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options SELECT planning where lower(option_name) expression lookup can stream autoload/option_id ordering from trailing index columns and avoid table reads for option_value.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
