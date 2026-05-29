<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next311 reports update set null not null child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent311', 'parent311', 2, 'CREATE TABLE parent311(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child311', 'child311', 3, 'CREATE TABLE child311(parent_id INTEGER NOT NULL REFERENCES parent311(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child311_parent_id', 'child311', 4, 'CREATE INDEX child311_parent_id ON child311(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_null_notnull_child_column');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next312 reports delete set null not null child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent312', 'parent312', 2, 'CREATE TABLE parent312(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child312', 'child312', 3, 'CREATE TABLE child312(parent_id INTEGER NOT NULL REFERENCES parent312(id) ON DELETE SET NULL)', 2),
        $record('index', 'child312_parent_id', 'child312', 4, 'CREATE INDEX child312_parent_id ON child312(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_null_notnull_child_column');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next313 reports update set default missing child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent313', 'parent313', 2, 'CREATE TABLE parent313(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child313', 'child313', 3, 'CREATE TABLE child313(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent313(id) ON UPDATE SET DEFAULT)', 2),
        $record('index', 'child313_parent_id', 'child313', 4, 'CREATE INDEX child313_parent_id ON child313(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_default_missing_child_default');

    $t->same(1, count($rows));
    $t->same('SET DEFAULT', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next314 reports delete set default missing child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent314', 'parent314', 2, 'CREATE TABLE parent314(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child314', 'child314', 3, 'CREATE TABLE child314(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent314(id) ON DELETE SET DEFAULT)', 2),
        $record('index', 'child314_parent_id', 'child314', 4, 'CREATE INDEX child314_parent_id ON child314(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_default_missing_child_default');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
};

$tests['pragma index xinfo foreignkey next311-314 page wrappers expose action deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent311p', 'parent311p', 2, 'CREATE TABLE parent311p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child311p', 'child311p', 3, 'CREATE TABLE child311p(parent_id INTEGER REFERENCES parent311p(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child311p_parent_id', 'child311p', 4, 'CREATE INDEX child311p_parent_id ON child311p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent311p', 'parent311p', 2, 'CREATE TABLE parent311p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child311p', 'child311p', 3, 'CREATE TABLE child311p(parent_id INTEGER NOT NULL REFERENCES parent311p(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child311p_parent_id', 'child311p', 4, 'CREATE INDEX child311p_parent_id ON child311p(parent_id)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page311(
        $current,
        $next,
        'PRAGMA index_xinfo(child311p_parent_id)',
        'PRAGMA foreign_key_list(child311p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next311', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next311']['update_set_null_notnull_child_column']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next311']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page314'));
};

return $tests;
