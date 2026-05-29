<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next271 reports cascade after current repair'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent271', 'parent271', 2, 'CREATE TABLE parent271(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child271', 'child271', 3, 'CREATE TABLE child271(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent271(id) ON DELETE CASCADE)', 2),
    ];
    $next = [
        ...$current,
        $record('index', 'child271_parent_id', 'child271', 4, 'CREATE INDEX child271_parent_id ON child271(parent_id)', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page271($current, $next, 'PRAGMA index_xinfo(child271_parent_id)', 'PRAGMA foreign_key_list(child271)');

    $t->same('pragma-index-xinfo-foreignkey-current-source-next271', $page['operation']);
    $t->same(1, $page['current']['foreign_key_child_action_lookup_after_current_next271']['repaired']);
    $t->same(0, $page['current']['foreign_key_child_action_lookup_after_current_next271']['regressed']);
    $t->same(true, $page['delta']['foreign_key_child_action_lookup_after_current_ready_next271']);
};

$tests['pragma index xinfo foreignkey next272 reports set null after current still blocked'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent272', 'parent272', 2, 'CREATE TABLE parent272(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child272', 'child272', 3, 'CREATE TABLE child272(parent_id INTEGER, active INTEGER, FOREIGN KEY(parent_id) REFERENCES parent272(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child272_parent_partial', 'child272', 4, 'CREATE INDEX child272_parent_partial ON child272(parent_id) WHERE active = 1', 3),
    ];
    $next = $current;

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page272($current, $next, 'PRAGMA index_xinfo(child272_parent_partial)', 'PRAGMA foreign_key_list(child272)');

    $t->same(1, $page['current']['foreign_key_child_action_lookup_after_current_next272']['still_blocked']);
    $t->same(false, $page['delta']['foreign_key_child_action_lookup_after_current_ready_next272']);
};

$tests['pragma index xinfo foreignkey next273 reports set default after current regression'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent273', 'parent273', 2, 'CREATE TABLE parent273(slug TEXT PRIMARY KEY)', 1),
        $record('index', 'sqlite_autoindex_parent273_1', 'parent273', 3, null, 2),
        $record('table', 'child273', 'child273', 4, "CREATE TABLE child273(slug TEXT DEFAULT 'none', FOREIGN KEY(slug) REFERENCES parent273(slug) ON DELETE SET DEFAULT)", 3),
        $record('index', 'child273_slug', 'child273', 5, 'CREATE INDEX child273_slug ON child273(slug)', 4),
    ];
    $next = array_slice($current, 0, 3);

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page273($current, $next, 'PRAGMA index_xinfo(child273_slug)', 'PRAGMA foreign_key_list(child273)');

    $t->same(1, $page['current']['foreign_key_child_action_lookup_after_current_next273']['regressed']);
    $t->same(false, $page['delta']['foreign_key_child_action_lookup_after_current_ready_next273']);
};

$tests['pragma index xinfo foreignkey next274 reports restrict after current unchanged ok and cursor'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent274', 'parent274', 2, 'CREATE TABLE parent274(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record('index', 'sqlite_autoindex_parent274_1', 'parent274', 3, null, 2),
        $record('table', 'child274', 'child274', 4, 'CREATE TABLE child274(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent274(a, b) ON UPDATE RESTRICT)', 3),
        $record('index', 'child274_a_b', 'child274', 5, 'CREATE INDEX child274_a_b ON child274(a, b)', 4),
    ];

    $first = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page274($records, $records, 'PRAGMA index_xinfo(child274_a_b)', 'PRAGMA foreign_key_list(child274)', 0, 2);
    $second = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page274($records, $records, 'PRAGMA index_xinfo(child274_a_b)', 'PRAGMA foreign_key_list(child274)', 2, 50, $first['next']);

    $t->same(1, $second['current']['foreign_key_child_action_lookup_after_current_next274']['unchanged_ok']);
    $t->same(true, $second['delta']['foreign_key_child_action_lookup_after_current_ready_next274']);
    $t->same(null, $second['next']);
};

return $tests;
