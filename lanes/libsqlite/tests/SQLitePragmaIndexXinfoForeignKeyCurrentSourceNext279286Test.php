<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next279 reports missing parent table'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'child279', 'child279', 2, 'CREATE TABLE child279(parent_id INTEGER REFERENCES parent279(id))', 1),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'missing_parent_table');

    $t->same(1, count($rows));
    $t->same('missing_parent_table', $rows[0]['status']);
    $t->same(['id'], $rows[0]['parent_columns']);
};

$tests['pragma index xinfo foreignkey next280 reports missing parent column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent280', 'parent280', 2, 'CREATE TABLE parent280(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child280', 'child280', 3, 'CREATE TABLE child280(parent_code TEXT REFERENCES parent280(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'missing_parent_column');

    $t->same(1, count($rows));
    $t->same('parent280', $rows[0]['parent']);
    $t->contains('missing_parent_column', $rows[0]['message']);
};

$tests['pragma index xinfo foreignkey next281 reports parent without unique key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent281', 'parent281', 2, 'CREATE TABLE parent281(code TEXT)', 1),
        $record('table', 'child281', 'child281', 3, 'CREATE TABLE child281(code TEXT REFERENCES parent281(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'no_unique_parent_key');

    $t->same(1, count($rows));
    $t->same('no_unique_parent_key', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next282 reports collation mismatch parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent282', 'parent282', 2, 'CREATE TABLE parent282(code TEXT)', 1),
        $record('index', 'parent282_code_nocase', 'parent282', 3, 'CREATE UNIQUE INDEX parent282_code_nocase ON parent282(code COLLATE NOCASE)', 2),
        $record('table', 'child282', 'child282', 4, 'CREATE TABLE child282(code TEXT REFERENCES parent282(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'collation_mismatch_parent_key');

    $t->same(1, count($rows));
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next283 reports partial unique parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent283', 'parent283', 2, 'CREATE TABLE parent283(code TEXT)', 1),
        $record('index', 'parent283_code_partial', 'parent283', 3, 'CREATE UNIQUE INDEX parent283_code_partial ON parent283(code) WHERE code IS NOT NULL', 2),
        $record('table', 'child283', 'child283', 4, 'CREATE TABLE child283(code TEXT REFERENCES parent283(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'partial_unique_parent_key');

    $t->same(1, count($rows));
    $t->same('partial_unique_parent_key', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next284 reports expression unique parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent284', 'parent284', 2, 'CREATE TABLE parent284(code TEXT)', 1),
        $record('index', 'parent284_lower_code', 'parent284', 3, 'CREATE UNIQUE INDEX parent284_lower_code ON parent284(lower(code))', 2),
        $record('table', 'child284', 'child284', 4, 'CREATE TABLE child284(code TEXT REFERENCES parent284(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'expression_unique_parent_key');

    $t->same(1, count($rows));
    $t->same('expression_unique_parent_key', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next285 reports implicit rowid parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent285', 'parent285', 2, 'CREATE TABLE parent285(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child285', 'child285', 3, 'CREATE TABLE child285(parent_id INTEGER REFERENCES parent285(rowid))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'implicit_rowid_parent_key');

    $t->same(1, count($rows));
    $t->same(['rowid'], $rows[0]['parent_columns']);
};

$tests['pragma index xinfo foreignkey next286 reports composite parent key order mismatch'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent286', 'parent286', 2, 'CREATE TABLE parent286(a TEXT, b TEXT)', 1),
        $record('index', 'parent286_b_a', 'parent286', 3, 'CREATE UNIQUE INDEX parent286_b_a ON parent286(b, a)', 2),
        $record('table', 'child286', 'child286', 4, 'CREATE TABLE child286(a TEXT, b TEXT, FOREIGN KEY(a, b) REFERENCES parent286(a, b))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'composite_parent_key_order_mismatch');

    $t->same(1, count($rows));
    $t->same(['a', 'b'], $rows[0]['parent_columns']);
};

$tests['pragma index xinfo foreignkey next279-286 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent279p', 'parent279p', 2, 'CREATE TABLE parent279p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child279p', 'child279p', 3, 'CREATE TABLE child279p(parent_id INTEGER REFERENCES parent279p(id))', 2),
    ];
    $next = [
        $record('table', 'child279p', 'child279p', 3, 'CREATE TABLE child279p(parent_id INTEGER REFERENCES parent279p(id))', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page279(
        $current,
        $next,
        'PRAGMA index_xinfo(parent279p_id)',
        'PRAGMA foreign_key_list(child279p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next279', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_parent_key_diagnostics_next279']['missing_parent_table']);
    $t->same(true, $page['delta']['foreign_key_parent_key_diagnostic_changed_next279']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page286'));
};

return $tests;
