<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next232 child action index must be leftmost'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent232', 'parent232', 2, 'CREATE TABLE parent232(a INTEGER, b INTEGER, PRIMARY KEY(a, b)) WITHOUT ROWID', 1),
        $record('index', 'sqlite_autoindex_parent232_1', 'parent232', 3, null, 2),
        $record('table', 'child232', 'child232', 4, 'CREATE TABLE child232(a INTEGER, b INTEGER, payload TEXT, FOREIGN KEY(a, b) REFERENCES parent232(a, b) ON DELETE CASCADE)', 3),
        $record('index', 'child232_payload_ab', 'child232', 5, 'CREATE INDEX child232_payload_ab ON child232(payload, a, b)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($records);

    $t->same(2, count($rows));
    $t->same('misordered_child_action_index', $rows[0]['status']);
    $t->same('child232_payload_ab', $rows[0]['misordered_child_index']);
    $t->same(['a', 'b'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next233 expression prefix child index is a blocker'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent233', 'parent233', 2, 'CREATE TABLE parent233(code TEXT PRIMARY KEY)', 1),
        $record('index', 'sqlite_autoindex_parent233_1', 'parent233', 3, null, 2),
        $record('table', 'child233', 'child233', 4, 'CREATE TABLE child233(code TEXT, meta_key TEXT, FOREIGN KEY(code) REFERENCES parent233(code))', 3),
        $record('index', 'child233_expr_code', 'child233', 5, 'CREATE INDEX child233_expr_code ON child233(lower(meta_key), code)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($records);

    $t->same(1, count($rows));
    $t->same('expression_prefix_child_index', $rows[0]['status']);
    $t->same('child233_expr_code', $rows[0]['expression_prefix_index']);
    $t->same(['lower(meta_key)'], $rows[0]['expression_terms']);
};

$tests['pragma index xinfo foreignkey next234 expression unique index cannot satisfy parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent234', 'parent234', 2, 'CREATE TABLE parent234(slug TEXT NOT NULL)', 1),
        $record('index', 'parent234_lower_slug_unique', 'parent234', 3, 'CREATE UNIQUE INDEX parent234_lower_slug_unique ON parent234(lower(slug))', 2),
        $record('table', 'child234', 'child234', 4, 'CREATE TABLE child234(slug TEXT, FOREIGN KEY(slug) REFERENCES parent234(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($records);

    $t->same(1, count($rows));
    $t->same('expression_parent_unique_index', $rows[0]['status']);
    $t->same('parent234_lower_slug_unique', $rows[0]['expression_unique_index']);
    $t->same(true, $rows[0]['index_column_is_expression']);
};

$tests['pragma index xinfo foreignkey next235 descending parent unique is visible but admissible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent235', 'parent235', 2, 'CREATE TABLE parent235(slug TEXT NOT NULL)', 1),
        $record('index', 'parent235_slug_desc_unique', 'parent235', 3, 'CREATE UNIQUE INDEX parent235_slug_desc_unique ON parent235(slug DESC)', 2),
        $record('table', 'child235', 'child235', 4, 'CREATE TABLE child235(slug TEXT, FOREIGN KEY(slug) REFERENCES parent235(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($records);

    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same(true, $rows[0]['index_column_desc']);
    $t->same(['slug'], $rows[0]['descending_key_columns']);
};

return $tests;
