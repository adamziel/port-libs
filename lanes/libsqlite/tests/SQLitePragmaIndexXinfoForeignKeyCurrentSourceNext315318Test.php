<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next315 reports update set default null not null child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent315', 'parent315', 2, 'CREATE TABLE parent315(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child315', 'child315', 3, 'CREATE TABLE child315(parent_id INTEGER NOT NULL DEFAULT NULL REFERENCES parent315(id) ON UPDATE SET DEFAULT)', 2),
        $record('index', 'child315_parent_id', 'child315', 4, 'CREATE INDEX child315_parent_id ON child315(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_default_null_notnull_child_default');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next316 reports delete set default null not null child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent316', 'parent316', 2, 'CREATE TABLE parent316(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child316', 'child316', 3, 'CREATE TABLE child316(parent_id INTEGER NOT NULL DEFAULT NULL REFERENCES parent316(id) ON DELETE SET DEFAULT)', 2),
        $record('index', 'child316_parent_id', 'child316', 4, 'CREATE INDEX child316_parent_id ON child316(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_default_null_notnull_child_default');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next317 reports update cascade without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent317', 'parent317', 2, 'CREATE TABLE parent317(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child317', 'child317', 3, 'CREATE TABLE child317(parent_id INTEGER REFERENCES parent317(id) ON UPDATE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_cascade_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('CASCADE', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next318 reports delete cascade without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent318', 'parent318', 2, 'CREATE TABLE parent318(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child318', 'child318', 3, 'CREATE TABLE child318(parent_id INTEGER REFERENCES parent318(id) ON DELETE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_cascade_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next315-318 page wrappers expose action deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent315p', 'parent315p', 2, 'CREATE TABLE parent315p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child315p', 'child315p', 3, 'CREATE TABLE child315p(parent_id INTEGER DEFAULT 0 REFERENCES parent315p(id) ON UPDATE SET DEFAULT)', 2),
        $record('index', 'child315p_parent_id', 'child315p', 4, 'CREATE INDEX child315p_parent_id ON child315p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent315p', 'parent315p', 2, 'CREATE TABLE parent315p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child315p', 'child315p', 3, 'CREATE TABLE child315p(parent_id INTEGER NOT NULL DEFAULT NULL REFERENCES parent315p(id) ON UPDATE SET DEFAULT)', 2),
        $record('index', 'child315p_parent_id', 'child315p', 4, 'CREATE INDEX child315p_parent_id ON child315p(parent_id)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page315(
        $current,
        $next,
        'PRAGMA index_xinfo(child315p_parent_id)',
        'PRAGMA foreign_key_list(child315p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next315', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next315']['update_set_default_null_notnull_child_default']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next315']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page318'));
};

return $tests;
