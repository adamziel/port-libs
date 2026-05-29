<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next263 reports cascade action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent263', 'parent263', 2, 'CREATE TABLE parent263(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child263', 'child263', 3, 'CREATE TABLE child263(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent263(id) ON DELETE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'CASCADE');

    $t->same(1, count($rows));
    $t->same('foreign_key_child_action_lookup_index', $rows[0]['kind']);
    $t->same('on_delete', $rows[0]['action_column']);
    $t->same('CASCADE', $rows[0]['action']);
    $t->same('missing_child_lookup_index', $rows[0]['lookup_status']);
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next264 reports set null action with partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent264', 'parent264', 2, 'CREATE TABLE parent264(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child264', 'child264', 3, 'CREATE TABLE child264(parent_id INTEGER, active INTEGER, FOREIGN KEY(parent_id) REFERENCES parent264(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child264_parent_partial', 'child264', 4, 'CREATE INDEX child264_parent_partial ON child264(parent_id) WHERE active = 1', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'SET NULL');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
    $t->same('SET NULL', $rows[0]['action']);
    $t->same('partial_child_lookup_index', $rows[0]['lookup_status']);
    $t->same('child264_parent_partial', $rows[0]['candidate_index']);
};

$tests['pragma index xinfo foreignkey next265 reports set default action with expression child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent265', 'parent265', 2, 'CREATE TABLE parent265(slug TEXT PRIMARY KEY)', 1),
        $record('index', 'sqlite_autoindex_parent265_1', 'parent265', 3, null, 2),
        $record('table', 'child265', 'child265', 4, "CREATE TABLE child265(slug TEXT DEFAULT 'none', FOREIGN KEY(slug) REFERENCES parent265(slug) ON DELETE SET DEFAULT)", 3),
        $record('index', 'child265_slug_expr', 'child265', 5, 'CREATE INDEX child265_slug_expr ON child265(lower(slug))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'SET DEFAULT');

    $t->same(1, count($rows));
    $t->same('SET DEFAULT', $rows[0]['action']);
    $t->same('expression_child_lookup_index', $rows[0]['lookup_status']);
    $t->same(1, $rows[0]['candidate_expression_columns']);
};

$tests['pragma index xinfo foreignkey next266 reports restrict action with misordered child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent266', 'parent266', 2, 'CREATE TABLE parent266(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record('index', 'sqlite_autoindex_parent266_1', 'parent266', 3, null, 2),
        $record('table', 'child266', 'child266', 4, 'CREATE TABLE child266(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent266(a, b) ON UPDATE RESTRICT)', 3),
        $record('index', 'child266_b_a', 'child266', 5, 'CREATE INDEX child266_b_a ON child266(b, a)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'RESTRICT');

    $t->same(1, count($rows));
    $t->same('RESTRICT', $rows[0]['action']);
    $t->same('misordered_child_lookup_index', $rows[0]['lookup_status']);
    $t->same(['b', 'a'], $rows[0]['candidate_columns']);
};

$tests['pragma index xinfo foreignkey next263-266 page wrappers expose real rows'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent263p', 'parent263p', 2, 'CREATE TABLE parent263p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child263p', 'child263p', 3, 'CREATE TABLE child263p(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent263p(id) ON DELETE CASCADE)', 2),
        $record('index', 'child263p_parent_id', 'child263p', 4, 'CREATE INDEX child263p_parent_id ON child263p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent263p', 'parent263p', 2, 'CREATE TABLE parent263p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child263p', 'child263p', 3, 'CREATE TABLE child263p(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent263p(id) ON DELETE CASCADE)', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page263(
        $current,
        $next,
        'PRAGMA index_xinfo(child263p_parent_id)',
        'PRAGMA foreign_key_list(child263p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next263', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_child_action_lookup_indexes_next263']['blocked']);
    $t->same(true, $page['delta']['foreign_key_child_action_lookup_index_changed_next263']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page266'));
};

return $tests;
