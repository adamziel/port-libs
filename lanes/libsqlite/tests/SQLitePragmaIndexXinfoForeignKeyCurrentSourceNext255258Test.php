<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next255 parent key collation mismatch is visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent255', 'parent255', 2, 'CREATE TABLE parent255(slug TEXT COLLATE NOCASE)', 1),
        $record('index', 'parent255_slug_binary', 'parent255', 3, 'CREATE UNIQUE INDEX parent255_slug_binary ON parent255(slug COLLATE BINARY)', 2),
        $record('table', 'child255', 'child255', 4, 'CREATE TABLE child255(slug TEXT, FOREIGN KEY(slug) REFERENCES parent255(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows255($records);

    $t->same(1, count($rows));
    $t->same('foreign_key_parent_key_collation', $rows[0]['kind']);
    $t->same('parent_key_collation_mismatch', $rows[0]['status']);
    $t->same(['NOCASE'], $rows[0]['parent_declared_collations']);
    $t->same(['BINARY'], $rows[0]['parent_index_collations']);
};

$tests['pragma index xinfo foreignkey next256 partial parent key blocker is visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent256', 'parent256', 2, 'CREATE TABLE parent256(slug TEXT)', 1),
        $record('index', 'parent256_slug_partial', 'parent256', 3, "CREATE UNIQUE INDEX parent256_slug_partial ON parent256(slug) WHERE slug <> ''", 2),
        $record('table', 'child256', 'child256', 4, 'CREATE TABLE child256(slug TEXT, FOREIGN KEY(slug) REFERENCES parent256(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentKeyRows256($records);

    $t->same(1, count($rows));
    $t->same('foreign_key_partial_parent_key', $rows[0]['kind']);
    $t->same('partial_parent_key', $rows[0]['status']);
    $t->same(true, $rows[0]['parent_index_partial']);
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next257 expression parent key blocker is visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent257', 'parent257', 2, 'CREATE TABLE parent257(slug TEXT)', 1),
        $record('index', 'parent257_lower_slug', 'parent257', 3, 'CREATE UNIQUE INDEX parent257_lower_slug ON parent257(lower(slug))', 2),
        $record('table', 'child257', 'child257', 4, 'CREATE TABLE child257(slug TEXT, FOREIGN KEY(slug) REFERENCES parent257(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows257($records);

    $t->same(1, count($rows));
    $t->same('foreign_key_expression_parent_key', $rows[0]['kind']);
    $t->same('expression_parent_key', $rows[0]['status']);
    $t->same(1, $rows[0]['parent_index_expression_columns']);
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next258 descending parent key is reported without blocking'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent258', 'parent258', 2, 'CREATE TABLE parent258(slug TEXT NOT NULL)', 1),
        $record('index', 'parent258_slug_desc', 'parent258', 3, 'CREATE UNIQUE INDEX parent258_slug_desc ON parent258(slug DESC)', 2),
        $record('table', 'child258', 'child258', 4, 'CREATE TABLE child258(slug TEXT, FOREIGN KEY(slug) REFERENCES parent258(slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::descendingParentKeyRows258($records);

    $t->same(1, count($rows));
    $t->same('foreign_key_descending_parent_key', $rows[0]['kind']);
    $t->same('descending_parent_key', $rows[0]['status']);
    $t->same(1, $rows[0]['parent_index_descending_columns']);
    $t->same(false, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey next255 258 pages are cumulative and paginated'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent258', 'parent258', 2, 'CREATE TABLE parent258(slug TEXT COLLATE NOCASE)', 1),
        $record('index', 'parent258_slug_binary', 'parent258', 3, 'CREATE UNIQUE INDEX parent258_slug_binary ON parent258(slug COLLATE BINARY)', 2),
        $record('table', 'child258', 'child258', 4, 'CREATE TABLE child258(slug TEXT, FOREIGN KEY(slug) REFERENCES parent258(slug))', 3),
    ];
    $next = [
        $record('table', 'parent258', 'parent258', 2, 'CREATE TABLE parent258(slug TEXT NOT NULL)', 1),
        $record('index', 'parent258_slug_desc', 'parent258', 3, 'CREATE UNIQUE INDEX parent258_slug_desc ON parent258(slug DESC)', 2),
        $record('table', 'child258', 'child258', 4, 'CREATE TABLE child258(slug TEXT, FOREIGN KEY(slug) REFERENCES parent258(slug))', 3),
    ];

    $full = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page258($current, $next, 'PRAGMA index_xinfo(parent258_slug_desc)', 'PRAGMA main.foreign_key_list(child258)');
    $first = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page258($current, $next, 'PRAGMA index_xinfo(parent258_slug_desc)', 'PRAGMA main.foreign_key_list(child258)', 0, $full['total'] - 1);
    $second = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page258($current, $next, 'PRAGMA index_xinfo(parent258_slug_desc)', 'PRAGMA main.foreign_key_list(child258)', $full['total'] - 1, 1, $first['next']);

    $t->same('pragma-index-xinfo-foreignkey-current-source-next258', $full['operation']);
    $t->same(true, in_array('sqlite-pragma-foreign-key-parent-key-collation', $full['dependencies'], true));
    $t->same(true, in_array('sqlite-pragma-foreign-key-descending-parent-key', $full['dependencies'], true));
    $t->same($full['total'] - 1, $first['count']);
    $t->same(1, $second['count']);
    $t->same(null, $second['next']);
};

return $tests;
