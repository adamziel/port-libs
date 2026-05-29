<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next319 reports update restrict without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent319', 'parent319', 2, 'CREATE TABLE parent319(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child319', 'child319', 3, 'CREATE TABLE child319(parent_id INTEGER REFERENCES parent319(id) ON UPDATE RESTRICT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_restrict_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
    $t->same('RESTRICT', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next320 reports delete restrict without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent320', 'parent320', 2, 'CREATE TABLE parent320(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child320', 'child320', 3, 'CREATE TABLE child320(parent_id INTEGER REFERENCES parent320(id) ON DELETE RESTRICT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_restrict_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
    $t->same('RESTRICT', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next321 reports update no action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent321', 'parent321', 2, 'CREATE TABLE parent321(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child321', 'child321', 3, 'CREATE TABLE child321(parent_id INTEGER REFERENCES parent321(id) ON UPDATE NO ACTION)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_no_action_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_update', $rows[0]['action_column']);
    $t->same('NO ACTION', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next322 reports delete no action without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent322', 'parent322', 2, 'CREATE TABLE parent322(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child322', 'child322', 3, 'CREATE TABLE child322(parent_id INTEGER REFERENCES parent322(id) ON DELETE NO ACTION)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_no_action_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('on_delete', $rows[0]['action_column']);
    $t->same('NO ACTION', $rows[0]['action']);
};

$tests['pragma index xinfo foreignkey next319-322 page wrappers expose action deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent319p', 'parent319p', 2, 'CREATE TABLE parent319p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child319p', 'child319p', 3, 'CREATE TABLE child319p(parent_id INTEGER REFERENCES parent319p(id) ON UPDATE RESTRICT)', 2),
        $record('index', 'child319p_parent_id', 'child319p', 4, 'CREATE INDEX child319p_parent_id ON child319p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent319p', 'parent319p', 2, 'CREATE TABLE parent319p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child319p', 'child319p', 3, 'CREATE TABLE child319p(parent_id INTEGER REFERENCES parent319p(id) ON UPDATE RESTRICT)', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page319(
        $current,
        $next,
        'PRAGMA index_xinfo(child319p_parent_id)',
        'PRAGMA foreign_key_list(child319p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next319', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next319']['update_restrict_without_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next319']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page322'));
};

return $tests;
