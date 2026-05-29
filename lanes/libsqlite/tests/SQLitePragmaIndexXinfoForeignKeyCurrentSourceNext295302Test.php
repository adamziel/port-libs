<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next295 reports child parent affinity mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent295', 'parent295', 2, 'CREATE TABLE parent295(code INTEGER PRIMARY KEY)', 1),
        $record('table', 'child295', 'child295', 3, 'CREATE TABLE child295(code TEXT NOT NULL DEFAULT \'0\' REFERENCES parent295(code))', 2),
        $record('index', 'child295_code', 'child295', 4, 'CREATE INDEX child295_code ON child295(code)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'child_parent_affinity_mismatch');

    $t->same(1, count($rows));
    $t->same('child_parent_affinity_mismatch', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next296 reports child parent collation mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent296', 'parent296', 2, 'CREATE TABLE parent296(code TEXT COLLATE BINARY PRIMARY KEY)', 1),
        $record('table', 'child296', 'child296', 3, 'CREATE TABLE child296(code TEXT COLLATE NOCASE NOT NULL DEFAULT \'\' REFERENCES parent296(code))', 2),
        $record('index', 'child296_code', 'child296', 4, 'CREATE INDEX child296_code ON child296(code)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'child_parent_collation_mismatch');

    $t->same(1, count($rows));
    $t->same(['code'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next297 reports composite nullable partial child key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent297', 'parent297', 2, 'CREATE TABLE parent297(a INTEGER, b INTEGER, PRIMARY KEY(a, b))', 1),
        $record('table', 'child297', 'child297', 3, 'CREATE TABLE child297(a INTEGER NOT NULL DEFAULT 0, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent297(a, b))', 2),
        $record('index', 'child297_a_b', 'child297', 4, 'CREATE INDEX child297_a_b ON child297(a, b)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'composite_child_nullable_partial_key');

    $t->same(1, count($rows));
    $t->same(['a', 'b'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next298 reports self referential foreign key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'node298', 'node298', 2, 'CREATE TABLE node298(id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES node298(id))', 1),
        $record('index', 'node298_parent_id', 'node298', 3, 'CREATE INDEX node298_parent_id ON node298(parent_id)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'self_referential_foreign_key');

    $t->same(1, count($rows));
    $t->same('node298', $rows[0]['parent']);
};

$tests['pragma index xinfo foreignkey next299 reports cascading self reference'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'node299', 'node299', 2, 'CREATE TABLE node299(id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES node299(id) ON DELETE CASCADE)', 1),
        $record('index', 'node299_parent_id', 'node299', 3, 'CREATE INDEX node299_parent_id ON node299(parent_id)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'cascading_self_reference');

    $t->same(1, count($rows));
    $t->same(['CASCADE'], $rows[0]['actions']);
};

$tests['pragma index xinfo foreignkey next300 reports restrict without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent300', 'parent300', 2, 'CREATE TABLE parent300(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child300', 'child300', 3, 'CREATE TABLE child300(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent300(id) ON DELETE RESTRICT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'restrict_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('restrict_without_child_lookup_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next301 reports no action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent301', 'parent301', 2, 'CREATE TABLE parent301(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child301', 'child301', 3, 'CREATE TABLE child301(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent301(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'no_action_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same([], $rows[0]['actions']);
};

$tests['pragma index xinfo foreignkey next302 reports deferrable foreign key clause'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent302', 'parent302', 2, 'CREATE TABLE parent302(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child302', 'child302', 3, 'CREATE TABLE child302(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent302(id) DEFERRABLE INITIALLY DEFERRED)', 2),
        $record('index', 'child302_parent_id', 'child302', 4, 'CREATE INDEX child302_parent_id ON child302(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'deferrable_foreign_key_clause');

    $t->same(1, count($rows));
    $t->contains('deferrable_foreign_key_clause', $rows[0]['message']);
};

$tests['pragma index xinfo foreignkey next295-302 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent295p', 'parent295p', 2, 'CREATE TABLE parent295p(code TEXT PRIMARY KEY)', 1),
        $record('table', 'child295p', 'child295p', 3, 'CREATE TABLE child295p(code TEXT NOT NULL DEFAULT \'\' REFERENCES parent295p(code))', 2),
        $record('index', 'child295p_code', 'child295p', 4, 'CREATE INDEX child295p_code ON child295p(code)', 3),
    ];
    $next = [
        $record('table', 'parent295p', 'parent295p', 2, 'CREATE TABLE parent295p(code INTEGER PRIMARY KEY)', 1),
        $record('table', 'child295p', 'child295p', 3, 'CREATE TABLE child295p(code TEXT NOT NULL DEFAULT \'0\' REFERENCES parent295p(code))', 2),
        $record('index', 'child295p_code', 'child295p', 4, 'CREATE INDEX child295p_code ON child295p(code)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page295(
        $current,
        $next,
        'PRAGMA index_xinfo(child295p_code)',
        'PRAGMA foreign_key_list(child295p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next295', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_relationship_diagnostics_next295']['child_parent_affinity_mismatch']);
    $t->same(true, $page['delta']['foreign_key_relationship_diagnostic_changed_next295']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page302'));
};

return $tests;
