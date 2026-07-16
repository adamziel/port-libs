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
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%].priority'],
    ['column' => 'key', 'operator' => 'NOT IN', 'value' => ['name']],
    ['column' => 'limit', 'operator' => '=', 'value' => 5],
];

$plan = SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $constraints, [['column' => 'id']]);

echo json_encode([
    'scenario' => 'application-json-table-virtual-planner-constraints-current-next56',
    'idxStr' => $plan['idxStr'],
    'filterArguments' => $plan['filterArguments'],
    'filterCurrentNextColumns' => array_map(
        static fn (array $pair): array => [
            'current' => $pair['current']['column'],
            'next' => $pair['next']['column'] ?? null,
        ],
        $plan['filterCurrentNext'],
    ),
    'residualOperators' => array_column($plan['residual'], 'operator'),
    'applicationUse' => 'Copied wp_options JSON diagnostics can expose the SQLite virtual-table xFilter argv tape for json_tree() visible constraints while residual predicates remain outside the cursor argument stream.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
