<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7},{"name":"forms","priority":4}]}}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'key', 'operator' => 'NOT IN', 'value' => ['name']],
    ['column' => 'limit', 'operator' => '=', 'value' => 3],
];

$plan = SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $constraints, [['column' => 'id']]);

echo json_encode([
    'scenario' => 'application-json-table-xbestindex-current-next',
    'idxNum' => $plan['idxNum'],
    'idxStr' => $plan['idxStr'],
    'orderByConsumed' => $plan['orderByConsumed'],
    'constraintUsage' => $plan['constraintUsage'],
    'currentNextColumns' => array_map(
        static fn (array $pair): array => [
            'current' => $pair['current']['column'],
            'next' => $pair['next']['column'] ?? null,
        ],
        $plan['currentNext'],
    ),
    'estimatedRows' => $plan['estimatedRows'],
    'applicationUse' => 'Copied wp_options JSON diagnostics can expose SQLite-style xBestIndex constraint usage, residual predicates, and current/next cursor metadata before json_tree() scans run without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
