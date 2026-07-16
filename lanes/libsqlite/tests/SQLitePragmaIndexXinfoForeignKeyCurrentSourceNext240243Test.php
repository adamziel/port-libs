<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next240 implicit parent primary key mismatch is reported'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent240', 'parent240', 2, 'CREATE TABLE parent240(site_id INTEGER NOT NULL, term_id INTEGER NOT NULL, PRIMARY KEY(site_id, term_id))', 1),
        $record('table', 'child240', 'child240', 3, 'CREATE TABLE child240(legacy_parent INTEGER NOT NULL, FOREIGN KEY(legacy_parent) REFERENCES parent240)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows240($records);

    $t->same(1, count($rows));
    $t->same('parent_primary_key_arity_mismatch', $rows[0]['status']);
    $t->same(['site_id', 'term_id'], $rows[0]['parent_primary_key_columns']);
    $t->same(1, $rows[0]['child_key_arity']);
    $t->same(2, $rows[0]['parent_key_arity']);
};

$tests['pragma index xinfo foreignkey next241 implicit parent reference resolves primary key order'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent241', 'parent241', 2, 'CREATE TABLE parent241(a INTEGER, b INTEGER, PRIMARY KEY(b, a))', 1),
        $record('table', 'child241', 'child241', 3, 'CREATE TABLE child241(x INTEGER, y INTEGER, FOREIGN KEY(x, y) REFERENCES parent241)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($records);

    $t->same(2, count($rows));
    $t->same('ok_implicit_parent_primary_key', $rows[0]['status']);
    $t->same('b', $rows[0]['resolved_to']);
    $t->same('a', $rows[1]['resolved_to']);
    $t->same([ 'a', 'b' ], $rows[0]['parent_primary_key']);
};

$tests['pragma index xinfo foreignkey next242 rowid aliases are rejected unless declared'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent242', 'parent242', 2, 'CREATE TABLE parent242(slug TEXT NOT NULL UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent242_1', 'parent242', 3, null, 2),
        $record('table', 'child242', 'child242', 4, 'CREATE TABLE child242(parent_row INTEGER, FOREIGN KEY(parent_row) REFERENCES parent242(rowid))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($records);

    $t->same(1, count($rows));
    $t->same('rowid_alias_parent_key', $rows[0]['status']);
    $t->same('rowid', $rows[0]['rowid_alias']);
    $t->same(false, $rows[0]['parent_declares_column']);
    $t->same('sqlite_autoindex_parent242_1', $rows[0]['rowid_auxiliary_index']);
};

$tests['pragma index xinfo foreignkey next243 parent affinity mismatch is visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent243', 'parent243', 2, 'CREATE TABLE parent243(id INTEGER PRIMARY KEY, score NUMERIC)', 1),
        $record('table', 'child243', 'child243', 3, 'CREATE TABLE child243(parent_id TEXT REFERENCES parent243(id), score_text TEXT REFERENCES parent243(score))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyAffinityRows243($records);

    $t->same(2, count($rows));
    $t->same('affinity_mismatch', $rows[0]['status']);
    $t->same('TEXT', $rows[0]['child_affinity']);
    $t->same('INTEGER', $rows[0]['parent_affinity']);
    $t->same('NUMERIC', $rows[1]['parent_affinity']);
};

return $tests;
