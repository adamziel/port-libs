<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'valid_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'seo', 'priority' => 2],
                ['name' => 'cache', 'priority' => 7],
            ],
        ])),
    ],
    [
        'option_id' => 2,
        'option_name' => 'malformed_jsonb_settings',
        'option_value' => new SQLiteBlobValue("\x1c\x00"),
    ],
    [
        'option_id' => 3,
        'option_name' => 'text_json_settings',
        'option_value' => '{"rules":[{"name":"forms","priority":4}]}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'null_json_settings',
        'option_value' => null,
    ],
];

$innerSql = "SELECT o.option_name AS option_name, j.fullkey AS fullkey, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' ORDER BY priority DESC, option_name ASC";
$leftSql = "SELECT o.option_id AS id, o.option_name AS option_name, j.key AS json_key, j.atom AS priority FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' ORDER BY id ASC, priority DESC";
$validLiteralHex = bin2hex(SQLiteJsonB::encode([
    'rules' => [
        ['name' => 'seo', 'priority' => 2],
        ['name' => 'cache', 'priority' => 7],
    ],
]));
$literalSql = "SELECT key, atom AS priority, fullkey FROM json_tree(X'{$validLiteralHex}', '$.rules') WHERE key = 'priority' ORDER BY priority DESC";
$malformedLiteralSql = "SELECT key, atom FROM json_each WHERE json = X'1c00' AND root = '$.rules' ORDER BY key";

echo json_encode([
    'scenario' => 'application-select-sql-json-malformed-jsonb-join',
    'applicationUse' => 'Local-only wp_options diagnostics can join row-sourced and SQL-literal JSONB option blobs through parser-level json_tree(), while malformed JSONB and SQL NULL option values follow the validated planner path and produce empty or LEFT-join null-extended rowsets without aborting the copied import preview.',
    'innerSql' => $innerSql,
    'innerRows' => SQLiteSelectSql::execute($innerSql, ['wp_options' => $options]),
    'leftSql' => $leftSql,
    'leftRows' => SQLiteSelectSql::execute($leftSql, ['wp_options' => $options]),
    'literalSql' => $literalSql,
    'literalRows' => SQLiteSelectSql::execute($literalSql, []),
    'malformedLiteralSql' => $malformedLiteralSql,
    'malformedLiteralRows' => SQLiteSelectSql::execute($malformedLiteralSql, []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
