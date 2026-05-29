<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next259 reports missing child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent259', 'parent259', 2, 'CREATE TABLE parent259(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child259', 'child259', 3, 'CREATE TABLE child259(parent_id INTEGER REFERENCES parent259(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'missing_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('foreign_key_child_lookup_index', $rows[0]['kind']);
    $t->same('missing_child_lookup_index', $rows[0]['status']);
    $t->same(['parent_id'], $rows[0]['child_columns']);
    $t->same(null, $rows[0]['candidate_index']);
};

$tests['pragma index xinfo foreignkey next260 reports partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent260', 'parent260', 2, 'CREATE TABLE parent260(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child260', 'child260', 3, 'CREATE TABLE child260(parent_id INTEGER REFERENCES parent260(id), active INTEGER)', 2),
        $record('index', 'child260_parent_partial', 'child260', 4, 'CREATE INDEX child260_parent_partial ON child260(parent_id) WHERE active = 1', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'partial_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('partial_child_lookup_index', $rows[0]['status']);
    $t->same('child260_parent_partial', $rows[0]['candidate_index']);
    $t->same(true, $rows[0]['candidate_partial']);
};

$tests['pragma index xinfo foreignkey next261 reports expression child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent261', 'parent261', 2, 'CREATE TABLE parent261(slug TEXT PRIMARY KEY)', 1),
        $record('index', 'sqlite_autoindex_parent261_1', 'parent261', 3, null, 2),
        $record('table', 'child261', 'child261', 4, 'CREATE TABLE child261(slug TEXT REFERENCES parent261(slug))', 3),
        $record('index', 'child261_slug_expr', 'child261', 5, 'CREATE INDEX child261_slug_expr ON child261(lower(slug))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'expression_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('expression_child_lookup_index', $rows[0]['status']);
    $t->same('child261_slug_expr', $rows[0]['candidate_index']);
    $t->same(1, $rows[0]['candidate_expression_columns']);
};

$tests['pragma index xinfo foreignkey next262 reports misordered child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent262', 'parent262', 2, 'CREATE TABLE parent262(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record('index', 'sqlite_autoindex_parent262_1', 'parent262', 3, null, 2),
        $record('table', 'child262', 'child262', 4, 'CREATE TABLE child262(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent262(a, b))', 3),
        $record('index', 'child262_b_a', 'child262', 5, 'CREATE INDEX child262_b_a ON child262(b, a)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'misordered_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('misordered_child_lookup_index', $rows[0]['status']);
    $t->same('child262_b_a', $rows[0]['candidate_index']);
    $t->same(['b', 'a'], $rows[0]['candidate_columns']);
};

$tests['pragma index xinfo foreignkey next259-262 page wrappers expose real rows'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent259p', 'parent259p', 2, 'CREATE TABLE parent259p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child259p', 'child259p', 3, 'CREATE TABLE child259p(parent_id INTEGER REFERENCES parent259p(id))', 2),
        $record('index', 'child259p_parent_id', 'child259p', 4, 'CREATE INDEX child259p_parent_id ON child259p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent259p', 'parent259p', 2, 'CREATE TABLE parent259p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child259p', 'child259p', 3, 'CREATE TABLE child259p(parent_id INTEGER REFERENCES parent259p(id))', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page259(
        $current,
        $next,
        'PRAGMA index_xinfo(child259p_parent_id)',
        'PRAGMA foreign_key_list(child259p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next259', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_child_lookup_indexes_next259']['missing_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_child_lookup_index_changed_next259']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page262'));
};

return $tests;
