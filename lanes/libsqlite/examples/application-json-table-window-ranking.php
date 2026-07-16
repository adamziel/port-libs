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

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2,"autoload":true},{"name":"cache","priority":7,"autoload":false},{"name":"forms","priority":7,"autoload":true},{"name":"media","priority":1,"autoload":false}]}}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
];

$rows = SQLiteJsonTablePlan::windowedRows('json_tree', $constraints, [
    ['column' => 'atom', 'direction' => 'DESC'],
], [], 2);

echo json_encode([
    'scenario' => 'application-json-table-window-ranking',
    'planned' => [
        'rows' => array_map(
            static fn (array $row): array => [
                'fullkey' => $row['fullkey'],
                'priority' => $row['atom'],
                'rank' => $row['window_rank'],
                'denseRank' => $row['window_dense_rank'],
                'rowNumber' => $row['window_row_number'],
                'ntile' => $row['window_ntile'],
                'lagPriority' => $row['window_lag'],
                'leadPriority' => $row['window_lead'],
            ],
            $rows,
        ),
    ],
    'applicationUse' => 'Local-only wp_options diagnostics can expand copied plugin settings through json_tree() and preview SQLite window ranking over JSON table rows before import or repair tools require ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
