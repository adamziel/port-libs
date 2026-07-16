<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_autoload_present',
        'rootPage' => 41,
        'estimatedRows' => 900,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_autoload_present ON wp_options(lower(option_name)) WHERE autoload IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_lower_autoload_and_value_present',
        'rootPage' => 42,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_autoload_and_value_present ON wp_options(lower(option_name)) WHERE autoload IS NOT NULL AND option_value IS NOT NULL',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'siteurl'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'autoload']],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_value']],
    ],
];

$ranked = SQLiteSelectExpressionIndexPlan::rankedPlans(
    $indexes,
    $predicate,
    [['column' => 'option_name']],
    ['option_name', 'autoload', 'option_value']
);
$selected = $ranked[0] ?? null;

echo json_encode([
    'scenario' => 'application-select-expression-partial-is-not-null',
    'selectedIndex' => $selected['name'] ?? null,
    'rootPage' => $selected['rootPage'] ?? null,
    'partial' => (bool) ($selected['partial'] ?? false),
    'covering' => (bool) ($selected['covering'] ?? false),
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'estimatedCost' => $selected['estimatedCost'] ?? null,
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options SELECT planning where explicit autoload/option_value IS NOT NULL predicates make a partial lower(option_name) expression index safe without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
