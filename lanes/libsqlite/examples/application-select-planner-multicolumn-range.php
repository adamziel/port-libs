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
        'name' => 'idx_blog_name_autoload',
        'rootPage' => 81,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_blog_name_autoload ON wp_options(blog_id, option_name, autoload)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'blog_id'], 'right' => 1],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
        ['operator' => '<', 'left' => ['column' => 'autoload'], 'right' => 'z'],
    ],
];

$plan = SQLiteMultiColumnRangePlan::choose($indexes, $predicate, [['column' => 'option_name']]);

echo json_encode([
    'applicationScenario' => 'wp_options multicolumn index range planning keeps the first range as the current B-tree interval and leaves later range predicates as residual filters.',
    'index' => $plan['name'] ?? null,
    'usedColumns' => $plan['usedColumns'] ?? [],
    'rangeColumn' => $plan['rangeColumn'] ?? null,
    'residualRangeColumns' => $plan['residualRangeColumns'] ?? [],
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
