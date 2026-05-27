<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;

$tests = [];

$makeFirstPage = static function (int $pageSize = 1024, int $pageCount = 1): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 1), 44, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$snapshotFor = static function (callable $mutate, int $pageSize = 1024, int $pageCount = 3) use ($makeFirstPage): SQLitePragmaSnapshot {
    $page = $makeFirstPage($pageSize, $pageCount);
    $page = $mutate($page);

    return SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($page . str_repeat("\0", $pageSize * ($pageCount - 1))));
};

$pragmaCases = [
    'page size' => ['page_size', 1024, static fn (string $page): string => $page],
    'page count' => ['page_count', 3, static fn (string $page): string => $page],
    'freelist count' => ['freelist_count', 7, static fn (string $page): string => substr_replace($page, pack('N', 7), 36, 4)],
    'schema version' => ['schema_version', 42, static fn (string $page): string => substr_replace($page, pack('N', 42), 40, 4)],
    'data version' => ['data_version', 11, static fn (string $page): string => substr_replace($page, pack('N', 11), 24, 4)],
    'user version' => ['user_version', 19, static fn (string $page): string => substr_replace($page, pack('N', 19), 60, 4)],
    'application id' => ['application_id', 0x57504f50, static fn (string $page): string => substr_replace($page, pack('N', 0x57504f50), 68, 4)],
    'utf16le encoding' => ['encoding', 'UTF-16le', static fn (string $page): string => substr_replace($page, pack('N', 2), 56, 4)],
    'utf16be encoding' => ['encoding', 'UTF-16be', static fn (string $page): string => substr_replace($page, pack('N', 3), 56, 4)],
    'wal journal mode' => ['journal_mode', 'wal', static function (string $page): string {
        $page[18] = "\x02";
        $page[19] = "\x02";

        return $page;
    }],
    'full auto vacuum' => ['auto_vacuum', 'full', static fn (string $page): string => substr_replace($page, pack('N', 5), 52, 4)],
    'incremental auto vacuum' => ['auto_vacuum', 'incremental', static function (string $page): string {
        $page = substr_replace($page, pack('N', 5), 52, 4);

        return substr_replace($page, pack('N', 1), 64, 4);
    }],
];

foreach ($pragmaCases as $name => [$pragma, $expected, $mutate]) {
    $tests['schema pragma corpus snapshot ' . $name] = static function (TestRunner $t) use ($snapshotFor, $pragma, $expected, $mutate): void {
        $t->same($expected, $snapshotFor($mutate)->value($pragma));
    };
}

$lockingCases = [
    'default main query' => ['PRAGMA locking_mode', 'main', null, 'normal', false],
    'lowercase exclusive assignment' => ['PRAGMA locking_mode=exclusive', 'main', 'exclusive', 'exclusive', true],
    'parenthesized normal assignment' => ['PRAGMA locking_mode(normal)', 'main', 'normal', 'normal', false],
    'schema qualified attached assignment' => ['PRAGMA wp.locking_mode = exclusive', 'wp', 'exclusive', 'exclusive', true],
    'schema qualified attached query' => ['PRAGMA wp.locking_mode', 'wp', null, 'normal', false],
    'temp stays exclusive' => ['PRAGMA temp.locking_mode = normal', 'temp', 'normal', 'exclusive', false],
    'unknown mode noops' => ['PRAGMA locking_mode = invalid_mode', 'main', 'invalid_mode', 'normal', false],
    'trailing semicolon accepted' => [" PRAGMA main.locking_mode ;\n", 'main', null, 'normal', false],
];

foreach ($lockingCases as $name => [$sql, $schema, $requested, $mode, $changed]) {
    $tests['schema pragma corpus locking_mode ' . $name] = static function (TestRunner $t) use ($sql, $schema, $requested, $mode, $changed): void {
        $pragma = new SQLitePragmaLockingMode();
        $result = $pragma->execute($sql);

        $t->same([$schema, $requested, $mode, $changed, [['locking_mode' => $mode]]], [
            $result['schema'],
            $result['requested_mode'],
            $result['locking_mode'],
            $result['changed'],
            $result['rows'],
        ]);
    };
}

$tableCases = [
    'column unique' => ['CREATE TABLE wp_options(option_id integer primary key, option_name text UNIQUE, autoload text)', [['option_name']]],
    'table unique composite' => ['CREATE TABLE wp_options(option_name text, autoload text, UNIQUE(autoload, option_name))', [['autoload', 'option_name']]],
    'named table unique' => ['CREATE TABLE wp_options(option_name text, autoload text, CONSTRAINT uq UNIQUE("option_name" COLLATE nocase DESC))', [['option_name']]],
    'column primary key text' => ['CREATE TABLE wp_options(option_id integer, option_name text PRIMARY KEY, autoload text)', [['option_name']]],
    'integer primary key rowid skipped' => ['CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name text UNIQUE)', [['option_name']]],
    'integer primary key desc indexed' => ['CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY DESC, option_name text UNIQUE)', [['option_id'], ['option_name']]],
    'without rowid primary key row included' => ['CREATE TABLE wp_options(option_name text PRIMARY KEY, autoload text UNIQUE) WITHOUT ROWID', [['option_name'], ['autoload']]],
    'check expression ignored' => ["CREATE TABLE wp_options(option_name text CHECK(option_name <> 'UNIQUE'), autoload text UNIQUE)", [['autoload']]],
    'comments ignored around unique' => ["CREATE TABLE wp_options(option_name text /* UNIQUE ignored */ UNIQUE, autoload text -- PRIMARY KEY ignored\n)", [['option_name']]],
    'duplicate unique collapsed' => ['CREATE TABLE wp_options(option_name text UNIQUE, UNIQUE(option_name), autoload text)', [['option_name']]],
];

foreach ($tableCases as $name => [$sql, $expected]) {
    $tests['schema ddl corpus create table ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $actual = array_map(
            static fn (array $columns): array => array_map(static fn ($column): string => $column->columnName, $columns),
            SQLiteCreateTable::automaticIndexColumnMetadata($sql),
        );

        $t->same($expected, $actual);
    };
}

$indexCases = [
    'single quoted column' => ["CREATE INDEX idx_name ON main.wp_options('option_name')", ['option_name', 'BINARY', false, false]],
    'collate nocase desc' => ['CREATE INDEX idx_name ON wp_options(option_name COLLATE nocase DESC)', ['option_name', 'NOCASE', true, false]],
    'multi column first' => ['CREATE INDEX idx_autoload_name ON wp_options(autoload, option_name COLLATE nocase DESC)', ['autoload', 'BINARY', false, false]],
    'partial where literal' => ["CREATE INDEX idx_autoloaded ON wp_options(option_name) WHERE autoload='yes'", ['option_name', 'BINARY', false, true]],
    'partial where is not null' => ['CREATE INDEX idx_present ON wp_options(option_name) WHERE option_name IS NOT NULL', ['option_name', 'BINARY', false, true]],
    'schema qualified table' => ['CREATE INDEX idx_present ON main.wp_options(option_name)', ['option_name', 'BINARY', false, false]],
    'bracket quoted column' => ['CREATE INDEX idx_present ON wp_options([option_name] DESC)', ['option_name', 'BINARY', true, false]],
    'backtick quoted column' => ['CREATE INDEX idx_present ON wp_options(`autoload` ASC)', ['autoload', 'BINARY', false, false]],
];

foreach ($indexCases as $name => [$sql, $expected]) {
    $tests['schema ddl corpus create index ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $column = SQLiteCreateIndex::firstColumn($sql);
        $t->same($expected, [$column?->columnName, $column?->collation, $column?->descending, $column?->partial]);
    };
}

$schemaRows = [
    'table row' => [['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text)'], ['table', 'wp_options', 'wp_options', 2, false, true, false]],
    'index row' => [['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], ['index', 'wp_options_option_name', 'wp_options', 3, false, false, true]],
    'autoindex row' => [['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 4, null], ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 4, true, false, true]],
    'view row' => [['view', 'wp_active_options', 'wp_active_options', null, 'CREATE VIEW wp_active_options AS SELECT * FROM wp_options'], ['view', 'wp_active_options', 'wp_active_options', null, false, false, false]],
];

foreach ($schemaRows as $name => [$values, $expected]) {
    $tests['schema ddl corpus sqlite_schema record ' . $name] = static function (TestRunner $t) use ($values, $expected): void {
        $cell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode($values), 512);
        $record = SQLiteSchemaRecord::fromTableLeafCell(SQLiteTableLeafCell::parse(str_pad($cell, 512, "\0"), 0, 512), 1);

        $t->same($expected, [
            $record->type,
            $record->name,
            $record->tableName,
            $record->rootPage,
            $record->sql === null,
            $record->isTable('wp_options'),
            $record->isIndexForTable('wp_options'),
        ]);
    };
}

return $tests;
