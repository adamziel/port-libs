<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderCursor;

$tests = [];

$tests['numeric comparison wiring index leaf insert orders mixed storage classes'] = static function (TestRunner $t): void {
    $page = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode([null, 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode([10, 2])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['10', 3])),
        SQLiteIndexCell::encode(SQLiteRecord::encode([new SQLiteBlobValue('10'), 4])),
    ]);

    $page = SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock(
        SQLiteIndexLeafPage::deleteCellByRecordValues($page, ['10', 3]),
        ['2x', 5],
    );
    $cells = SQLiteIndexCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512));
    $t->same(['null:NULL:1', 'integer:10:2', 'text:10:4', 'text:2x:5'], array_map(
        static function (SQLiteIndexCell $cell): string {
            $values = $cell->record()->values;
            $key = $values[0];
            return match (true) {
                $key === null => 'null:NULL:' . $values[1],
                is_int($key) => 'integer:' . $key . ':' . $values[1],
                is_string($key) => 'text:' . $key . ':' . $values[1],
                $key instanceof SQLiteBlobValue => 'blob:' . $key->bytes . ':' . $values[1],
                default => 'unknown',
            };
        },
        $cells,
    ));
};

$tests['numeric comparison wiring foreign key check uses parent affinity and collation'] = static function (TestRunner $t): void {
    $tables = [
        'parent' => [
            ['id' => '9223372036854775808', 'slug' => 'plugin  ', 'blob_id' => 7],
        ],
        'child' => [
            ['rowid' => 1, 'parent_id' => 9.223372036854776E+18, 'slug' => 'plugin', 'blob_id' => new SQLiteBlobValue('7')],
            ['rowid' => 2, 'parent_id' => '9223372036854775808x', 'slug' => 'plugin', 'blob_id' => new SQLiteBlobValue('7')],
            ['rowid' => 3, 'parent_id' => 9.223372036854776E+18, 'slug' => 'plugin x', 'blob_id' => new SQLiteBlobValue('7')],
            ['rowid' => 4, 'parent_id' => 9.223372036854776E+18, 'slug' => 'plugin', 'blob_id' => new SQLiteBlobValue('7x')],
            ['rowid' => 5, 'parent_id' => null, 'slug' => 'ignored', 'blob_id' => null],
        ],
    ];
    $foreignKeys = [
        [
            'table' => 'child',
            'parent' => 'parent',
            'columns' => [
                ['child' => 'parent_id', 'parent' => 'id', 'affinity' => 'numeric'],
                ['child' => 'slug', 'parent' => 'slug', 'affinity' => 'text', 'collation' => 'rtrim'],
                ['child' => 'blob_id', 'parent' => 'blob_id', 'affinity' => 'numeric'],
            ],
        ],
    ];

    $t->same([
        ['table' => 'child', 'rowid' => 2, 'parent' => 'parent', 'fkid' => 0],
        ['table' => 'child', 'rowid' => 3, 'parent' => 'parent', 'fkid' => 0],
        ['table' => 'child', 'rowid' => 4, 'parent' => 'parent', 'fkid' => 0],
    ], SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys));
};

$tests['numeric comparison wiring aggregate order handles prefixes nulls blobs and collations'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([
        ['v' => 'null', 'k' => null, 'name' => 'z'],
        ['v' => 'integer', 'k' => 500, 'name' => 'z'],
        ['v' => 'prefix', 'k' => '5e2x', 'name' => 'z'],
        ['v' => 'overflow', 'k' => '9223372036854775808', 'name' => 'z'],
        ['v' => 'blob', 'k' => new SQLiteBlobValue('5'), 'name' => 'z'],
        ['v' => 'nocase', 'k' => '5e2x', 'name' => 'A'],
    ], 'v', ['k', 'name'], null, 'CG', ['BINARY', 'NOCASE']);

    $t->same(['null', 'blob', 'integer', 'overflow', 'nocase', 'prefix'], $cursor->values());
};

return $tests;
