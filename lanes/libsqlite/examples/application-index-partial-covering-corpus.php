<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mode = $argv[1] ?? 'autoloaded-siteurl';

$indexes = [
    [
        'name' => 'idx_wp_options_name_partial',
        'rootPage' => 21,
        'estimatedRows' => 12000,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_wp_options_name_partial ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_name_covering',
        'rootPage' => 22,
        'estimatedRows' => 320,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_wp_options_name_covering ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_transient_desc',
        'rootPage' => 23,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_wp_options_transient_desc ON wp_options(upper(option_name) DESC) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_wp_options_schema_version',
        'rootPage' => 24,
        'estimatedRows' => 640,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => 'CREATE INDEX idx_wp_options_schema_version ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL',
    ],
];

$predicate = match ($mode) {
    'transient-range' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '>=', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT_'],
            ['operator' => '<', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT`'],
        ],
    ],
    'schema-version' => [
        'operator' => 'BETWEEN',
        'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
        'lower' => 1,
        'upper' => 999999,
    ],
    default => [
        'operator' => '=',
        'left' => ['function' => 'lower', 'column' => 'option_name'],
        'right' => 'siteurl',
    ],
};

$orderBy = $mode === 'transient-range'
    ? [['column' => 'option_name', 'direction' => 'DESC']]
    : [['column' => 'option_name']];
$neededColumns = $mode === 'schema-version'
    ? ['option_value', 'autoload']
    : ['option_name', 'autoload'];

$ranked = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes, $predicate, $orderBy, $neededColumns);
$selected = $ranked[0] ?? null;

echo json_encode([
    'scenario' => 'application-index-partial-covering-corpus',
    'mode' => $mode,
    'selectedIndex' => $selected['name'] ?? null,
    'rootPage' => $selected['rootPage'] ?? null,
    'operator' => $selected['operator'] ?? null,
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'estimatedCost' => $selected['estimatedCost'] ?? null,
    'partial' => (bool) ($selected['partial'] ?? false),
    'covering' => (bool) ($selected['covering'] ?? false),
    'orderBySatisfied' => (bool) ($selected['orderBySatisfied'] ?? false),
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'covering' => (bool) ($plan['covering'] ?? false),
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options planner choices for partial expression indexes that also cover projected columns, avoiding table b-tree fetches for option-name, transient, and schema-version probes without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
