<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next228 desc parent unique remains admissible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent228', 'parent228', 2, 'CREATE TABLE parent228(code TEXT COLLATE NOCASE NOT NULL)', 1),
        $record('index', 'parent228_code_desc_unique', 'parent228', 3, 'CREATE UNIQUE INDEX parent228_code_desc_unique ON parent228(code COLLATE NOCASE DESC)', 2),
        $record('table', 'child228', 'child228', 4, 'CREATE TABLE child228(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES parent228(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($records);

    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same(true, $rows[0]['index_column_desc']);
    $t->same(true, $rows[0]['sort_order_ignored_for_fk']);
};

$tests['pragma index xinfo foreignkey next229 wider unique prefix is not exact parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent229', 'parent229', 2, 'CREATE TABLE parent229(code TEXT NOT NULL, locale TEXT NOT NULL)', 1),
        $record('index', 'parent229_code_locale_unique', 'parent229', 3, 'CREATE UNIQUE INDEX parent229_code_locale_unique ON parent229(code, locale)', 2),
        $record('table', 'child229', 'child229', 4, 'CREATE TABLE child229(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES parent229(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($records);

    $t->same(1, count($rows));
    $t->same('wider_parent_unique_index', $rows[0]['status']);
    $t->same(['locale'], $rows[0]['extra_index_columns']);
};

$tests['pragma index xinfo foreignkey next230 explicit rowid parent reference is blocked'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent230', 'parent230', 2, 'CREATE TABLE parent230(id INTEGER PRIMARY KEY, title TEXT)', 1),
        $record('table', 'child230', 'child230', 3, 'CREATE TABLE child230(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent230(rowid))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($records);

    $t->same(1, count($rows));
    $t->same('pseudo_rowid_parent_key', $rows[0]['status']);
    $t->same(false, $rows[0]['declared_parent_column']);
};

$tests['pragma index xinfo foreignkey next231 expression unique index is not a parent key'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent231', 'parent231', 2, 'CREATE TABLE parent231(code TEXT NOT NULL)', 1),
        $record('index', 'parent231_lower_code_unique', 'parent231', 3, 'CREATE UNIQUE INDEX parent231_lower_code_unique ON parent231(lower(code))', 2),
        $record('table', 'child231', 'child231', 4, 'CREATE TABLE child231(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES parent231(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($records);

    $t->same(1, count($rows));
    $t->same('expression_unique_index', $rows[0]['status']);
    $t->same(true, $rows[0]['index_column_is_expression']);
    $t->same(['seqno-0'], $rows[0]['index_expression_terms']);
};

return $tests;
