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
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'key', 'operator' => 'IN', 'value' => ['priority']],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%].priority'],
];

$plan = SQLiteJsonTablePlan::plan('json_tree', $constraints);
$rows = SQLiteJsonTablePlan::filteredRows('json_tree', $constraints);

echo json_encode([
    'scenario' => 'application-json-table-visible-constraint-pushdown',
    'planned' => [
        'usedColumns' => array_column($plan['used'], 'column'),
        'visiblePushdownColumns' => array_values(array_map(
            static fn (array $constraint): string => $constraint['column'],
            array_filter($plan['used'], static fn (array $constraint): bool => ($constraint['constraint'] ?? null) === 'VISIBLE'),
        )),
        'residualColumns' => array_column($plan['residual'], 'column'),
        'estimatedRows' => $plan['estimatedRows'],
        'estimatedCost' => $plan['estimatedCost'],
        'priorityRows' => array_map(
            static fn (array $row): array => [
                'rowid' => $row['id'],
                'key' => $row['key'],
                'priority' => $row['atom'],
                'fullkey' => $row['fullkey'],
            ],
            $rows,
        ),
    ],
    'applicationUse' => 'Copied wp_options plugin settings can advertise visible json_tree() constraints to the planner while still applying residual checks before migration diagnostics run without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
