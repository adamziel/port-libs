<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicateName = $argv[1] ?? 'lower-siteurl';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_name',
        'rootPage' => 7,
        'sql' => 'CREATE INDEX idx_wp_options_lower_name ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_name_length',
        'rootPage' => 9,
        'sql' => 'CREATE INDEX idx_wp_options_name_length ON wp_options(length(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_value_integer',
        'rootPage' => 10,
        'sql' => 'CREATE INDEX idx_wp_options_value_integer ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL',
    ],
];

$predicate = match ($predicateName) {
    'length-band' => [
        'operator' => 'BETWEEN',
        'left' => ['function' => 'length', 'column' => 'option_name'],
        'lower' => 4,
        'upper' => 18,
    ],
    'db-version' => [
        'operator' => '=',
        'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
        'right' => 58796,
    ],
    default => [
        'operator' => '=',
        'left' => ['function' => 'lower', 'column' => 'option_name'],
        'right' => 'siteurl',
    ],
};

$plan = SQLiteSelectExpressionIndexPlan::choose($indexes, $predicate);

echo json_encode([
    'scenario' => 'application-select-expression-index-plan',
    'predicate' => $predicateName,
    'selectedIndex' => $plan['name'] ?? null,
    'rootPage' => $plan['rootPage'] ?? null,
    'type' => $plan['type'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'values' => $plan['values'] ?? null,
    'partialPredicateSatisfied' => (bool) ($plan['partial'] ?? false),
    'residualPredicateRequired' => (bool) ($plan['residualPredicateRequired'] ?? false),
    'applicationUse' => 'Preview copied wp_options SELECT WHERE expression-index dispatch for lower(option_name), length(option_name), and CAST(option_value AS INTEGER) predicates before row decoding without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
