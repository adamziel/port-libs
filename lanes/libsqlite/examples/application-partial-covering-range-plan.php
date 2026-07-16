<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$indexes = [
    [
        'name' => 'idx_autoloaded_plugin_options',
        'rootPage' => 110,
        'estimatedRows' => 2400,
        'sql' => "CREATE INDEX idx_autoloaded_plugin_options ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_full_autoload_name',
        'rootPage' => 111,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_full_autoload_name ON wp_options(autoload, option_name)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
        ['operator' => '<', 'left' => ['column' => 'option_value'], 'right' => 'z'],
    ],
];

$plan = SQLiteMultiColumnRangePlan::choose(
    $indexes,
    $predicate,
    [['column' => 'option_name']],
    ['autoload', 'option_name', 'option_value']
);

echo json_encode([
    'applicationScenario' => 'autoloaded wp_options plugin scan uses a proved partial covering index, keeps option_name as the current range, and leaves option_value as a residual next-column predicate.',
    'index' => $plan['name'] ?? null,
    'partial' => $plan['partial'] ?? false,
    'covering' => $plan['covering'] ?? false,
    'usedColumns' => $plan['usedColumns'] ?? [],
    'rangeColumn' => $plan['rangeColumn'] ?? null,
    'residualRangeColumns' => $plan['residualRangeColumns'] ?? [],
    'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
