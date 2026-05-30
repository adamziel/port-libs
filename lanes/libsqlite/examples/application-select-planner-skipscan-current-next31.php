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
        'name' => 'idx_autoload_blog_name',
        'rootPage' => 131,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 3],
        'sql' => 'CREATE INDEX idx_autoload_blog_name ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'blog_id'], 'right' => 1],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
        ['operator' => '<', 'left' => ['column' => 'option_value'], 'right' => 'z'],
    ],
];

$plan = SQLiteMultiColumnRangePlan::choose($indexes, $predicate, [
    ['column' => 'autoload'],
    ['column' => 'blog_id'],
    ['column' => 'option_name'],
]);

echo json_encode([
    'applicationScenario' => 'Copied wp_options maintenance queries can plan a skip-scan over idx_autoload_blog_name when autoload is unconstrained but blog_id is equal and option_name is the current range.',
    'index' => $plan['name'] ?? null,
    'usesSkipScan' => $plan['usesSkipScan'] ?? false,
    'skippedColumns' => $plan['skippedColumns'] ?? [],
    'skipScanLoops' => $plan['skipScanLoops'] ?? null,
    'usedColumns' => $plan['usedColumns'] ?? [],
    'rangeColumn' => $plan['rangeColumn'] ?? null,
    'residualRangeColumns' => $plan['residualRangeColumns'] ?? [],
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'estimatedCost' => $plan['estimatedCost'] ?? null,
    'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
