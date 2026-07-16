<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next303 reports empty child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent303', 'parent303', 2, 'CREATE TABLE parent303(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child303', 'child303', 3, 'CREATE TABLE child303(parent_id INTEGER, FOREIGN KEY(" ") REFERENCES parent303(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'empty_child_column');

    $t->same(1, count($rows));
    $t->same([' '], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next304 reports nullable cascade child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent304', 'parent304', 2, 'CREATE TABLE parent304(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child304', 'child304', 3, 'CREATE TABLE child304(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent304(id) ON DELETE CASCADE)', 2),
        $record('index', 'child304_parent_id', 'child304', 4, 'CREATE INDEX child304_parent_id ON child304(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'nullable_cascade_child_column');

    $t->same(1, count($rows));
    $t->same(['CASCADE'], $rows[0]['actions']);
};

$tests['pragma index xinfo foreignkey next305 reports child lookup collation mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent305', 'parent305', 2, 'CREATE TABLE parent305(slug TEXT PRIMARY KEY)', 1),
        $record('table', 'child305', 'child305', 3, 'CREATE TABLE child305(parent_slug TEXT NOT NULL DEFAULT "" REFERENCES parent305(slug))', 2),
        $record('index', 'child305_parent_slug_nocase', 'child305', 4, 'CREATE INDEX child305_parent_slug_nocase ON child305(parent_slug COLLATE NOCASE)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_collation_mismatch');

    $t->same(1, count($rows));
    $t->contains('child_lookup_collation_mismatch', $rows[0]['message']);
};

$tests['pragma index xinfo foreignkey next306 reports child lookup desc mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent306', 'parent306', 2, 'CREATE TABLE parent306(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child306', 'child306', 3, 'CREATE TABLE child306(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent306(id))', 2),
        $record('index', 'child306_parent_id_desc', 'child306', 4, 'CREATE INDEX child306_parent_id_desc ON child306(parent_id DESC)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_desc_mismatch');

    $t->same(1, count($rows));
    $t->same('child_lookup_desc_mismatch', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next303-306 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent303p', 'parent303p', 2, 'CREATE TABLE parent303p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child303p', 'child303p', 3, 'CREATE TABLE child303p(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent303p(id))', 2),
        $record('index', 'child303p_parent_id', 'child303p', 4, 'CREATE INDEX child303p_parent_id ON child303p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent303p', 'parent303p', 2, 'CREATE TABLE parent303p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child303p', 'child303p', 3, 'CREATE TABLE child303p(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent303p(id) ON DELETE CASCADE)', 2),
        $record('index', 'child303p_parent_id', 'child303p', 4, 'CREATE INDEX child303p_parent_id ON child303p(parent_id)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page304(
        $current,
        $next,
        'PRAGMA index_xinfo(child303p_parent_id)',
        'PRAGMA foreign_key_list(child303p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next304', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_child_key_diagnostics_next304']['nullable_cascade_child_column']);
    $t->same(true, $page['delta']['foreign_key_child_key_diagnostic_changed_next304']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page306'));
};

return $tests;
