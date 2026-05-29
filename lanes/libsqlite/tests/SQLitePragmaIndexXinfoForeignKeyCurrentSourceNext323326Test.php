<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next323 reports update restrict partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent323', 'parent323', 2, 'CREATE TABLE parent323(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child323', 'child323', 3, 'CREATE TABLE child323(parent_id INTEGER REFERENCES parent323(id) ON UPDATE RESTRICT)', 2),
        $record('index', 'child323_parent_id_partial', 'child323', 4, 'CREATE INDEX child323_parent_id_partial ON child323(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_restrict_partial_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
    $t->same('RESTRICT', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next324 reports delete restrict partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent324', 'parent324', 2, 'CREATE TABLE parent324(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child324', 'child324', 3, 'CREATE TABLE child324(parent_id INTEGER REFERENCES parent324(id) ON DELETE RESTRICT)', 2),
        $record('index', 'child324_parent_id_partial', 'child324', 4, 'CREATE INDEX child324_parent_id_partial ON child324(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_restrict_partial_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
    $t->same('RESTRICT', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next325 reports update no action partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent325', 'parent325', 2, 'CREATE TABLE parent325(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child325', 'child325', 3, 'CREATE TABLE child325(parent_id INTEGER REFERENCES parent325(id) ON UPDATE NO ACTION)', 2),
        $record('index', 'child325_parent_id_partial', 'child325', 4, 'CREATE INDEX child325_parent_id_partial ON child325(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_no_action_partial_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
    $t->same('NO ACTION', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next326 reports delete no action partial child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent326', 'parent326', 2, 'CREATE TABLE parent326(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child326', 'child326', 3, 'CREATE TABLE child326(parent_id INTEGER REFERENCES parent326(id) ON DELETE NO ACTION)', 2),
        $record('index', 'child326_parent_id_partial', 'child326', 4, 'CREATE INDEX child326_parent_id_partial ON child326(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_no_action_partial_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
    $t->same('NO ACTION', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next323-326 page wrappers expose action deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent323p', 'parent323p', 2, 'CREATE TABLE parent323p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child323p', 'child323p', 3, 'CREATE TABLE child323p(parent_id INTEGER REFERENCES parent323p(id) ON UPDATE RESTRICT)', 2),
        $record('index', 'child323p_parent_id', 'child323p', 4, 'CREATE INDEX child323p_parent_id ON child323p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent323p', 'parent323p', 2, 'CREATE TABLE parent323p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child323p', 'child323p', 3, 'CREATE TABLE child323p(parent_id INTEGER REFERENCES parent323p(id) ON UPDATE RESTRICT)', 2),
        $record('index', 'child323p_parent_id_partial', 'child323p', 4, 'CREATE INDEX child323p_parent_id_partial ON child323p(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page323(
        $current,
        $next,
        'PRAGMA index_xinfo(child323p_parent_id_partial)',
        'PRAGMA foreign_key_list(child323p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next323', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next323']['update_restrict_partial_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next323']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page326'));
};

return $tests;
