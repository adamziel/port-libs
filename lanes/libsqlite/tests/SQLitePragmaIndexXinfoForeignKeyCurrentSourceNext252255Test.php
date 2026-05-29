<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next252 missing child columns are reported from table_xinfo'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent252', 'parent252', 2, 'CREATE TABLE parent252(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent252_1', 'parent252', 3, null, 2),
        $record('index', 'sqlite_autoindex_parent252_2', 'parent252', 4, null, 3),
        $record('table', 'child252', 'child252', 5, 'CREATE TABLE child252(slug_ref TEXT REFERENCES parent252(slug), FOREIGN KEY(taxonomy_ref) REFERENCES parent252(taxonomy))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252($records);

    $t->same(1, count($rows));
    $t->same('foreign_key_missing_child_column', $rows[0]['kind']);
    $t->same('missing_child_column', $rows[0]['status']);
    $t->same('taxonomy_ref', $rows[0]['from']);
    $t->same(['slug_ref'], $rows[0]['available_child_columns']);
};

$tests['pragma index xinfo foreignkey next253 generated set action blockers are visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent253', 'parent253', 2, 'CREATE TABLE parent253(slug TEXT PRIMARY KEY, term_id INTEGER UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent253_1', 'parent253', 3, null, 2),
        $record('index', 'sqlite_autoindex_parent253_2', 'parent253', 4, null, 3),
        $record('table', 'child253', 'child253', 5, 'CREATE TABLE child253(raw_slug TEXT, raw_id INTEGER, slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES parent253(slug) ON DELETE SET NULL, term_ref INTEGER GENERATED ALWAYS AS (raw_id) STORED NOT NULL, FOREIGN KEY(term_ref) REFERENCES parent253(term_id) ON UPDATE SET DEFAULT)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildActionRows253($records);

    $t->same(2, count($rows));
    $t->same('foreign_key_generated_child_action', $rows[0]['kind']);
    $t->same('set_null_generated_notnull_child', $rows[0]['status']);
    $t->same('virtual', $rows[0]['child_generated_storage']);
    $t->same('set_default_generated_null_child', $rows[1]['status']);
    $t->same('stored', $rows[1]['child_generated_storage']);
};

$tests['pragma index xinfo foreignkey next254 nullable parent unique key blockers are visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent254', 'parent254', 2, 'CREATE TABLE parent254(slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, UNIQUE(slug, taxonomy))', 1),
        $record('index', 'sqlite_autoindex_parent254_1', 'parent254', 3, null, 2),
        $record('table', 'child254', 'child254', 4, 'CREATE TABLE child254(slug TEXT, taxonomy TEXT, FOREIGN KEY(slug, taxonomy) REFERENCES parent254(slug, taxonomy) ON DELETE CASCADE)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($records);

    $t->same(2, count($rows));
    $t->same('foreign_key_nullable_parent_key', $rows[0]['kind']);
    $t->same('nullable_parent_key', $rows[0]['status']);
    $t->same(true, $rows[0]['blocked']);
    $t->same('sqlite_autoindex_parent254_1', $rows[0]['parent_unique_index']);
    $t->same(['slug', 'taxonomy'], $rows[0]['parent_index_columns']);
    $t->same(['NOCASE', 'RTRIM'], $rows[0]['parent_index_collations']);
};

$tests['pragma index xinfo foreignkey next252-255 handoff reaches the PRAGMA next255 source'] = static function (TestRunner $t): void {
    $available = get_class_methods(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class);

    $t->same(true, in_array('page252', $available, true));
    $t->same(true, in_array('page253', $available, true));
    $t->same(true, in_array('page254', $available, true));
    $t->same(true, in_array('page255', $available, true));
};

return $tests;
