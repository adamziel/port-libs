<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next307 reports set null not null child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent307', 'parent307', 2, 'CREATE TABLE parent307(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child307', 'child307', 3, 'CREATE TABLE child307(parent_id INTEGER NOT NULL REFERENCES parent307(id) ON DELETE SET NULL)', 2),
        $record('index', 'child307_parent_id', 'child307', 4, 'CREATE INDEX child307_parent_id ON child307(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_null_notnull_child_column');

    $t->same(1, count($rows));
    $t->same('set_null_notnull_child_column', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next308 reports set default missing child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent308', 'parent308', 2, 'CREATE TABLE parent308(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child308', 'child308', 3, 'CREATE TABLE child308(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent308(id) ON UPDATE SET DEFAULT)', 2),
        $record('index', 'child308_parent_id', 'child308', 4, 'CREATE INDEX child308_parent_id ON child308(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_default_missing_child_default');

    $t->same(1, count($rows));
    $t->same(['parent_id'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next309 reports cascade without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent309', 'parent309', 2, 'CREATE TABLE parent309(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child309', 'child309', 3, 'CREATE TABLE child309(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent309(id) ON DELETE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'cascade_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same(['CASCADE'], $rows[0]['actions']);
};

$tests['pragma index xinfo foreignkey next310 reports set default without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent310', 'parent310', 2, 'CREATE TABLE parent310(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child310', 'child310', 3, 'CREATE TABLE child310(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent310(id) ON DELETE SET DEFAULT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_default_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('set_default_without_child_lookup_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next307-310 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent307p', 'parent307p', 2, 'CREATE TABLE parent307p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child307p', 'child307p', 3, 'CREATE TABLE child307p(parent_id INTEGER REFERENCES parent307p(id) ON DELETE SET NULL)', 2),
        $record('index', 'child307p_parent_id', 'child307p', 4, 'CREATE INDEX child307p_parent_id ON child307p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent307p', 'parent307p', 2, 'CREATE TABLE parent307p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child307p', 'child307p', 3, 'CREATE TABLE child307p(parent_id INTEGER NOT NULL REFERENCES parent307p(id) ON DELETE SET NULL)', 2),
        $record('index', 'child307p_parent_id', 'child307p', 4, 'CREATE INDEX child307p_parent_id ON child307p(parent_id)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page307(
        $current,
        $next,
        'PRAGMA index_xinfo(child307p_parent_id)',
        'PRAGMA foreign_key_list(child307p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next307', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_relationship_diagnostics_next307']['set_null_notnull_child_column']);
    $t->same(true, $page['delta']['foreign_key_relationship_diagnostic_changed_next307']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page310'));
};

return $tests;
