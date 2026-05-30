<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicateName = $argv[1] ?? 'lower-siteurl';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name_broad',
        'rootPage' => 7,
        'estimatedRows' => 12000,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_broad ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_lower_name_covering',
        'rootPage' => 8,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_name_covering ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_upper_name_desc',
        'rootPage' => 9,
        'estimatedRows' => 90,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_wp_options_upper_name_desc ON wp_options(upper(option_name) DESC) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_value_integer',
        'rootPage' => 10,
        'estimatedRows' => 800,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => 'CREATE INDEX idx_wp_options_value_integer ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL',
    ],
];

$predicate = match ($predicateName) {
    'upper-transient-range' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '>=', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT_'],
            ['operator' => '<', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT`'],
        ],
    ],
    'db-version-range' => [
        'operator' => 'BETWEEN',
        'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
        'lower' => 100,
        'upper' => 60000,
    ],
    default => [
        'operator' => '=',
        'left' => ['function' => 'lower', 'column' => 'option_name'],
        'right' => 'siteurl',
    ],
};

$orderBy = $predicateName === 'upper-transient-range'
    ? [['column' => 'option_name', 'direction' => 'DESC']]
    : [['column' => 'option_name']];
$neededColumns = $predicateName === 'db-version-range'
    ? ['option_value', 'autoload']
    : ['option_name', 'autoload'];

$ranked = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes, $predicate, $orderBy, $neededColumns);
$selected = $ranked[0] ?? null;

echo json_encode([
    'scenario' => 'application-select-expression-index-cost',
    'predicate' => $predicateName,
    'selectedIndex' => $selected['name'] ?? null,
    'rootPage' => $selected['rootPage'] ?? null,
    'operator' => $selected['operator'] ?? null,
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'estimatedCost' => $selected['estimatedCost'] ?? null,
    'covering' => (bool) ($selected['covering'] ?? false),
    'orderBySatisfied' => (bool) ($selected['orderBySatisfied'] ?? false),
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options SELECT planner cost decisions for competing expression/range indexes before native row decoding, choosing point/range scans that satisfy covering and ORDER BY requirements without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
