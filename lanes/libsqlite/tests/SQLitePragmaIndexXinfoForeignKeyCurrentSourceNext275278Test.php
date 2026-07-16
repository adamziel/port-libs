<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next275 reports cascade without child lookup index'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent275', 'parent275', 2, 'CREATE TABLE parent275(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child275', 'child275', 3, 'CREATE TABLE child275(parent_id INTEGER REFERENCES parent275(id) ON DELETE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyActionRows275($records, 'next', 'cascade_without_child_lookup_index');

    $t->same(1, count($rows));
    $t->same('foreign_key_action_index_xinfo', $rows[0]['kind']);
    $t->same('cascade_without_child_lookup_index', $rows[0]['status']);
    $t->same('missing_child_lookup_index', $rows[0]['child_lookup_status']);
    $t->same('CASCADE', $rows[0]['on_delete']);
};

$tests['pragma index xinfo foreignkey next276 reports set null notnull child column'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent276', 'parent276', 2, 'CREATE TABLE parent276(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child276', 'child276', 3, 'CREATE TABLE child276(parent_id INTEGER NOT NULL REFERENCES parent276(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child276_parent_id', 'child276', 4, 'CREATE INDEX child276_parent_id ON child276(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyActionRows275($records, 'next', 'set_null_notnull_child_column');

    $t->same(1, count($rows));
    $t->same('set_null_notnull_child_column', $rows[0]['status']);
    $t->same(['parent_id'], $rows[0]['child_notnull_columns']);
    $t->same('SET NULL', $rows[0]['on_update']);
};

$tests['pragma index xinfo foreignkey next277 reports set default missing child default'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent277', 'parent277', 2, 'CREATE TABLE parent277(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child277', 'child277', 3, 'CREATE TABLE child277(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent277(id) ON DELETE SET DEFAULT)', 2),
        $record('index', 'child277_parent_id', 'child277', 4, 'CREATE INDEX child277_parent_id ON child277(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyActionRows275($records, 'next', 'set_default_missing_child_default');

    $t->same(1, count($rows));
    $t->same('set_default_missing_child_default', $rows[0]['status']);
    $t->same([], $rows[0]['child_default_columns']);
    $t->same('SET DEFAULT', $rows[0]['on_delete']);
};

$tests['pragma index xinfo foreignkey next278 reports restrict and no action rows'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent278', 'parent278', 2, 'CREATE TABLE parent278(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child278', 'child278', 3, 'CREATE TABLE child278(parent_id INTEGER REFERENCES parent278(id) ON DELETE RESTRICT)', 2),
        $record('index', 'child278_parent_id', 'child278', 4, 'CREATE INDEX child278_parent_id ON child278(parent_id)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyActionRows275($records, 'next', 'restrict_or_no_action_fk');

    $t->same(1, count($rows));
    $t->same('restrict_or_no_action_fk', $rows[0]['status']);
    $t->same(false, $rows[0]['blocked']);
    $t->same('RESTRICT', $rows[0]['on_delete']);
};

$tests['pragma index xinfo foreignkey next275-278 page wrappers expose current source rows'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent275p', 'parent275p', 2, 'CREATE TABLE parent275p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child275p', 'child275p', 3, 'CREATE TABLE child275p(parent_id INTEGER REFERENCES parent275p(id) ON DELETE CASCADE)', 2),
        $record('index', 'child275p_parent_id', 'child275p', 4, 'CREATE INDEX child275p_parent_id ON child275p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent275p', 'parent275p', 2, 'CREATE TABLE parent275p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child275p', 'child275p', 3, 'CREATE TABLE child275p(parent_id INTEGER REFERENCES parent275p(id) ON DELETE CASCADE)', 2),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page275(
        $current,
        $next,
        'PRAGMA index_xinfo(child275p_parent_id)',
        'PRAGMA foreign_key_list(child275p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next275', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_index_xinfo_next275']['cascade_without_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_index_xinfo_changed_next275']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page278'));
};

return $tests;
