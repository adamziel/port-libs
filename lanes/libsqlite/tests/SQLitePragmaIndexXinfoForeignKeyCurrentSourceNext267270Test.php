<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next267 reports restrict action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent267', 'parent267', 2, 'CREATE TABLE parent267(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child267', 'child267', 3, 'CREATE TABLE child267(parent_id INTEGER REFERENCES parent267(id) ON DELETE RESTRICT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'restrict_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('foreign_key_action_child_lookup_index', $rows[0]['kind']);
    $t->same('RESTRICT', $rows[0]['action']);
    $t->same(['parent_id'], $rows[0]['child_columns']);
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next268 reports cascade action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent268', 'parent268', 2, 'CREATE TABLE parent268(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child268', 'child268', 3, 'CREATE TABLE child268(parent_id INTEGER REFERENCES parent268(id) ON UPDATE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'cascade_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('CASCADE', $rows[0]['action']);
    $t->same('cascade_without_child_lookup_index', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey next269 reports set null action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent269', 'parent269', 2, 'CREATE TABLE parent269(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child269', 'child269', 3, 'CREATE TABLE child269(parent_id INTEGER REFERENCES parent269(id) ON DELETE SET NULL)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'set_null_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('SET NULL', $rows[0]['action']);
    $t->contains('PRAGMA index_xinfo', $rows[0]['message']);
};

$tests['pragma index xinfo foreignkey next270 reports set default action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent270', 'parent270', 2, 'CREATE TABLE parent270(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child270', 'child270', 3, 'CREATE TABLE child270(parent_id INTEGER DEFAULT 0 REFERENCES parent270(id) ON UPDATE SET DEFAULT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'set_default_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('SET DEFAULT', $rows[0]['action']);
    $t->same(null, $rows[0]['candidate_index']);
};

$tests['pragma index xinfo foreignkey next267-270 ignores indexed action children'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent267ok', 'parent267ok', 2, 'CREATE TABLE parent267ok(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child267ok', 'child267ok', 3, 'CREATE TABLE child267ok(parent_id INTEGER REFERENCES parent267ok(id) ON DELETE CASCADE)', 2),
        $record('index', 'child267ok_parent_id', 'child267ok', 4, 'CREATE INDEX child267ok_parent_id ON child267ok(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records);

    $t->same([], $rows);
};

$tests['pragma index xinfo foreignkey next267-270 page wrappers expose deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent267p', 'parent267p', 2, 'CREATE TABLE parent267p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child267p', 'child267p', 3, 'CREATE TABLE child267p(parent_id INTEGER REFERENCES parent267p(id) ON DELETE RESTRICT)', 2),
        $record('index', 'child267p_parent_id', 'child267p', 4, 'CREATE INDEX child267p_parent_id ON child267p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent267p', 'parent267p', 2, 'CREATE TABLE parent267p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child267p', 'child267p', 3, 'CREATE TABLE child267p(parent_id INTEGER REFERENCES parent267p(id) ON DELETE RESTRICT)', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page267(
        $current,
        $next,
        'PRAGMA index_xinfo(child267p_parent_id)',
        'PRAGMA foreign_key_list(child267p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next267', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_child_lookup_indexes_next267']['restrict_without_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_child_lookup_index_changed_next267']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page270'));
};

return $tests;
