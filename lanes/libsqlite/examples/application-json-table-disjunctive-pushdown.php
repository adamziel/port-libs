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

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2,"autoload":true},{"name":"cache","priority":7,"autoload":false},{"name":"forms","priority":4,"autoload":true}],"meta":{"owner":"admin"}}}';
$baseConstraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
];
$alternatives = [
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'name'],
        ['column' => 'type', 'operator' => '=', 'value' => 'text'],
        ['column' => 'atom', 'operator' => 'IN', 'value' => ['seo', 'forms']],
    ],
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 4],
    ],
    [
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%].autoload'],
        ['column' => 'atom', 'operator' => 'IS', 'value' => 1],
    ],
];

$plan = SQLiteJsonTablePlan::alternativePlan('json_tree', $baseConstraints, $alternatives);
$rows = SQLiteJsonTablePlan::filteredAlternativeRows('json_tree', $baseConstraints, $alternatives);

echo json_encode([
    'scenario' => 'application-json-table-disjunctive-pushdown',
    'planned' => [
        'branches' => count($plan['branches']),
        'usedColumnsByBranch' => array_map(
            static fn (array $branch): array => array_column($branch['used'], 'column'),
            $plan['branches'],
        ),
        'estimatedRows' => $plan['estimatedRows'],
        'estimatedCost' => $plan['estimatedCost'],
    ],
    'rows' => array_map(
        static fn (array $row): array => [
            'key' => $row['key'],
            'atom' => $row['atom'],
            'type' => $row['type'],
            'fullkey' => $row['fullkey'],
        ],
        $rows,
    ),
    'applicationUse' => 'Copied wp_options plugin settings can plan OR-shaped json_tree() diagnostics as separate visible-column pushdown branches, preserving duplicate suppression and residual row semantics without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
