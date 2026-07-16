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
require __DIR__ . '/../src/SQLiteJsonTableCursor.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTableCursor;

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2,"autoload":true},{"name":"cache","priority":7,"autoload":false},{"name":"forms","priority":4,"autoload":true}]}}';

$cursor = SQLiteJsonTableCursor::open('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
]);

$priorityRows = [];
while (!$cursor->eof()) {
    $priorityRows[] = [
        'rowid' => $cursor->rowid(),
        'key' => $cursor->column('key'),
        'priority' => $cursor->column('atom'),
        'fullkey' => $cursor->column('fullkey'),
    ];
    $cursor->next();
}

$malformedCursor = SQLiteJsonTableCursor::open('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => new SQLiteBlobValue("\x8b\xff" . str_repeat("\0", 7))],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
]);

$jsonbCursor = SQLiteJsonTableCursor::open('json_each', [
    ['column' => 'json', 'operator' => '=', 'value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['flags' => ['alpha', 'beta']]]))],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.flags'],
]);

echo json_encode([
    'scenario' => 'application-json-table-cursor',
    'planned' => [
        'function' => $cursor->functionName(),
        'priorityRows' => $priorityRows,
        'jsonbFlags' => array_map(
            static fn (array $row): array => [
                'rowid' => $row['id'],
                'key' => $row['key'],
                'flag' => $row['atom'],
            ],
            $jsonbCursor->all(),
        ),
        'malformedJsonb' => [
            'runnable' => $malformedCursor->plan()['runnable'],
            'jsonValid' => $malformedCursor->plan()['jsonValid'],
            'jsonError' => $malformedCursor->plan()['jsonError'],
            'rows' => $malformedCursor->count(),
        ],
    ],
    'applicationUse' => 'Local-only wp_options diagnostics can iterate json_tree()/json_each() through a bounded virtual-table cursor, including JSONB payloads and malformed-BLOB planning, before a full native VDBE cursor is available.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
