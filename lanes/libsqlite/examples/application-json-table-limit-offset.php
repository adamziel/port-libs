<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugin":{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false},{"name":"forms","enabled":true}]}}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ['column' => 'limit', 'operator' => '=', 'value' => 2],
    ['column' => 'offset', 'operator' => '=', 'value' => 1],
];

$rows = SQLiteJsonTablePlan::projectedRows('json_tree', $constraints, ['rowid', 'key', 'atom', 'fullkey']);

echo json_encode([
    'scenario' => 'application-json-table-limit-offset',
    'planned' => [
        'used' => array_map(
            static fn (array $constraint): array => [
                'column' => $constraint['column'],
                'constraint' => $constraint['constraint'] ?? null,
                'omit' => $constraint['omit'],
            ],
            SQLiteJsonTablePlan::plan('json_tree', $constraints)['used'],
        ),
        'rows' => $rows,
    ],
    'applicationUse' => 'Local-only wp_options diagnostics preserve SQLite JSON virtual-table LIMIT/OFFSET pushdown while filtering copied plugin settings rows, so paged JSON scans can be previewed without ext/sqlite before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
