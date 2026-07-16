<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicateName = $argv[1] ?? 'autoloaded-siteurl';

$indexes = [
    [
        'name' => 'idx_wp_options_lower_autoloaded',
        'rootPage' => 31,
        'estimatedRows' => 240,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_wp_options_lower_autoloaded ON wp_options(lower(option_name)) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_core_name_lengths',
        'rootPage' => 32,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_wp_options_core_name_lengths ON wp_options(length(option_name)) WHERE option_name IN ('siteurl','home','blogname')",
    ],
    [
        'name' => 'idx_wp_options_numeric_autoloaded',
        'rootPage' => 33,
        'estimatedRows' => 300,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => "CREATE INDEX idx_wp_options_numeric_autoloaded ON wp_options(CAST(option_value AS INTEGER)) WHERE autoload='yes' AND option_value IS NOT NULL",
    ],
];

$predicate = match ($predicateName) {
    'core-name-lengths' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => 'IN', 'left' => ['function' => 'length', 'column' => 'option_name'], 'values' => [4, 7, 8]],
            ['operator' => 'IN', 'left' => ['column' => 'option_name'], 'values' => ['siteurl', 'home', 'blogname']],
        ],
    ],
    'db-version' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['function' => 'cast_integer', 'column' => 'option_value'], 'right' => 58796],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
    default => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'siteurl'],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
};

$neededColumns = $predicateName === 'db-version'
    ? ['option_value', 'autoload']
    : ['option_name', 'autoload'];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes,
    $predicate,
    [['column' => $predicateName === 'db-version' ? 'option_value' : 'option_name']],
    $neededColumns
);

echo json_encode([
    'scenario' => 'application-select-expression-partial-covering-index',
    'predicate' => $predicateName,
    'selectedIndex' => $plan['name'] ?? null,
    'rootPage' => $plan['rootPage'] ?? null,
    'type' => $plan['type'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'values' => $plan['values'] ?? null,
    'partialPredicateSatisfied' => (bool) ($plan['partial'] ?? false),
    'covering' => (bool) ($plan['covering'] ?? false),
    'residualPredicateRequired' => (bool) ($plan['residualPredicateRequired'] ?? false),
    'applicationUse' => 'Preview copied wp_options SELECT planning where ordinary WHERE terms prove partial expression indexes safe, while covering metadata avoids table payload reads before native row decoding.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
