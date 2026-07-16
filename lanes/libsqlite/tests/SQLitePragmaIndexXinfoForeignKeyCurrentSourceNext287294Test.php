<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next287 reports missing child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent287', 'parent287', 2, 'CREATE TABLE parent287(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child287', 'child287', 3, 'CREATE TABLE child287(parent_id INTEGER, FOREIGN KEY(parent_missing) REFERENCES parent287(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'missing_child_column');

    $t->same(1, count($rows));
    $t->same(['parent_missing'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next288 reports generated child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent288', 'parent288', 2, 'CREATE TABLE parent288(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child288', 'child288', 3, 'CREATE TABLE child288(raw_id INTEGER, parent_id INTEGER GENERATED ALWAYS AS (raw_id) VIRTUAL, FOREIGN KEY(parent_id) REFERENCES parent288(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'generated_child_column');

    $t->same(1, count($rows));
    $t->same('generated_child_column', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next289 reports nullable set null child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent289', 'parent289', 2, 'CREATE TABLE parent289(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child289', 'child289', 3, 'CREATE TABLE child289(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent289(id) ON DELETE SET NULL)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'nullable_child_column');

    $t->same(1, count($rows));
    $t->same(['SET NULL'], $rows[0]['actions']);
};

$tests['pragma index xinfo foreignkey next290 reports missing set default child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent290', 'parent290', 2, 'CREATE TABLE parent290(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child290', 'child290', 3, 'CREATE TABLE child290(parent_id INTEGER NOT NULL, FOREIGN KEY(parent_id) REFERENCES parent290(id) ON DELETE SET DEFAULT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'missing_child_default');

    $t->same(1, count($rows));
    $t->contains('missing_child_default', $rows[0]['message']);
};

$tests['pragma index xinfo foreignkey next291 reports missing child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent291', 'parent291', 2, 'CREATE TABLE parent291(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child291', 'child291', 3, 'CREATE TABLE child291(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent291(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_missing_index');

    $t->same(1, count($rows));
    $t->same('child_lookup_missing_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next292 reports partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent292', 'parent292', 2, 'CREATE TABLE parent292(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child292', 'child292', 3, 'CREATE TABLE child292(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent292(id))', 2),
        $record('index', 'child292_parent_partial', 'child292', 4, 'CREATE INDEX child292_parent_partial ON child292(parent_id) WHERE parent_id > 0', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_partial_index');

    $t->same(1, count($rows));
    $t->same('child_lookup_partial_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next293 reports expression child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent293', 'parent293', 2, 'CREATE TABLE parent293(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child293', 'child293', 3, 'CREATE TABLE child293(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent293(id))', 2),
        $record('index', 'child293_parent_expr', 'child293', 4, 'CREATE INDEX child293_parent_expr ON child293(abs(parent_id))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_expression_index');

    $t->same(1, count($rows));
    $t->same('child_lookup_expression_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next294 reports child lookup order mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent294', 'parent294', 2, 'CREATE TABLE parent294(a INTEGER, b INTEGER, PRIMARY KEY(a, b))', 1),
        $record('table', 'child294', 'child294', 3, 'CREATE TABLE child294(a INTEGER NOT NULL DEFAULT 0, b INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(a, b) REFERENCES parent294(a, b))', 2),
        $record('index', 'child294_b_a', 'child294', 4, 'CREATE INDEX child294_b_a ON child294(b, a)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_order_mismatch');

    $t->same(1, count($rows));
    $t->same(['a', 'b'], $rows[0]['child_columns']);
};

$tests['pragma index xinfo foreignkey next287-294 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent287p', 'parent287p', 2, 'CREATE TABLE parent287p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child287p', 'child287p', 3, 'CREATE TABLE child287p(parent_id INTEGER NOT NULL DEFAULT 0 REFERENCES parent287p(id))', 2),
        $record('index', 'child287p_parent_id', 'child287p', 4, 'CREATE INDEX child287p_parent_id ON child287p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent287p', 'parent287p', 2, 'CREATE TABLE parent287p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child287p', 'child287p', 3, 'CREATE TABLE child287p(parent_id INTEGER, FOREIGN KEY(parent_missing) REFERENCES parent287p(id))', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page287(
        $current,
        $next,
        'PRAGMA index_xinfo(child287p_parent_id)',
        'PRAGMA foreign_key_list(child287p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next287', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_child_key_diagnostics_next287']['missing_child_column']);
    $t->same(true, $page['delta']['foreign_key_child_key_diagnostic_changed_next287']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page294'));
};

return $tests;
