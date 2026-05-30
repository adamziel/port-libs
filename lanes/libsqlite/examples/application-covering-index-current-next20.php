<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mode = $argv[1] ?? 'autoloaded-siteurl';

$indexes = [
    [
        'name' => 'idx_wp_options_name',
        'rootPage' => 31,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_wp_options_name ON wp_options(option_name)',
    ],
    [
        'name' => 'idx_wp_options_name_autoload_value',
        'rootPage' => 32,
        'estimatedRows' => 1200,
        'sql' => 'CREATE INDEX idx_wp_options_name_autoload_value ON wp_options(option_name, autoload, option_value)',
    ],
    [
        'name' => 'idx_wp_options_autoload_name_desc',
        'rootPage' => 33,
        'estimatedRows' => 9000,
        'sql' => 'CREATE INDEX idx_wp_options_autoload_name_desc ON wp_options(autoload, option_name DESC)',
    ],
    [
        'name' => 'idx_wp_options_public_name_value',
        'rootPage' => 34,
        'estimatedRows' => 800,
        'sql' => "CREATE INDEX idx_wp_options_public_name_value ON wp_options(option_name, option_value) WHERE autoload='yes'",
    ],
];

$predicate = match ($mode) {
    'autoloaded-range' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
            ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
        ],
    ],
    'public-siteurl' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['column' => 'option_name'], 'right' => 'siteurl'],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
    default => ['operator' => '=', 'left' => ['column' => 'option_name'], 'right' => 'siteurl'],
};

$neededColumns = $mode === 'public-siteurl'
    ? ['option_name', 'option_value']
    : ['option_name', 'autoload', 'option_value'];
$orderBy = $mode === 'autoloaded-range'
    ? [['column' => 'option_name', 'direction' => 'DESC']]
    : [];

$ranked = SQLiteCoveringIndexPlan::rankedPlans($indexes, $predicate, $neededColumns, $orderBy);
$selected = $ranked[0] ?? null;

echo json_encode([
    'scenario' => 'application-covering-index-current-next20',
    'mode' => $mode,
    'selectedIndex' => $selected['name'] ?? null,
    'rootPage' => $selected['rootPage'] ?? null,
    'usedColumns' => $selected['usedColumns'] ?? [],
    'rangeColumn' => $selected['rangeColumn'] ?? null,
    'covering' => (bool) ($selected['covering'] ?? false),
    'partial' => (bool) ($selected['partial'] ?? false),
    'orderBySatisfied' => (bool) ($selected['orderBySatisfied'] ?? false),
    'estimatedRows' => $selected['estimatedRows'] ?? null,
    'estimatedCost' => $selected['estimatedCost'] ?? null,
    'rankedIndexes' => array_map(
        static fn (array $plan): array => [
            'name' => $plan['name'],
            'estimatedRows' => $plan['estimatedRows'],
            'estimatedCost' => $plan['estimatedCost'],
            'covering' => $plan['covering'],
            'orderBySatisfied' => $plan['orderBySatisfied'],
        ],
        $ranked
    ),
    'applicationUse' => 'Preview copied wp_options covering-index choices for option lookups and autoloaded transient scans without table b-tree fetches or ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
