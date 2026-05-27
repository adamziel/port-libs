<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$compositeSql = <<<'SQL'
CREATE TABLE "wp site options"(
    blog_id INTEGER NOT NULL,
    option_name TEXT COLLATE nocase NOT NULL,
    locale TEXT COLLATE rtrim DEFAULT 'en_US',
    option_value TEXT,
    CONSTRAINT wp_site_options_pk PRIMARY KEY(blog_id ASC, option_name COLLATE binary DESC),
    UNIQUE(option_name, locale COLLATE nocase)
) WITHOUT ROWID
SQL;

$columnPrimarySql = <<<'SQL'
CREATE TABLE wp_network_options(
    network_id INTEGER PRIMARY KEY,
    option_name TEXT UNIQUE,
    option_value TEXT
) WITHOUT ROWID
SQL;

$quotedSql = <<<'SQL'
CREATE TABLE [wp tenant options](
    [tenant id] INTEGER NOT NULL,
    `option name` TEXT COLLATE nocase NOT NULL,
    "autoload flag" TEXT COLLATE rtrim NOT NULL,
    payload TEXT CHECK(payload <> 'PRIMARY KEY(tenant)'),
    CONSTRAINT [pk tenant option] PRIMARY KEY([tenant id], `option name` DESC, "autoload flag" COLLATE binary)
) /* table option */ WITHOUT /* comment gap */ ROWID
SQL;

$columnNames = static fn (array $columns): array => array_map(
    static fn (SQLiteIndexColumn $column): string => $column->columnName,
    $columns,
);
$columnCollations = static fn (array $columns): array => array_map(
    static fn (SQLiteIndexColumn $column): string => $column->collation,
    $columns,
);
$columnDirections = static fn (array $columns): array => array_map(
    static fn (SQLiteIndexColumn $column): bool => $column->descending,
    $columns,
);

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord('table', 'wp site options', 'wp site options', 2, $compositeSql, 1),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp site options_1', 'wp site options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp site options_2', 'wp site options', 4, null, 3),
    new SQLiteSchemaRecord('table', 'wp tenant options', 'wp tenant options', 5, $quotedSql, 4),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp tenant options_1', 'wp tenant options', 6, null, 5),
]);

$tests = [];

foreach ([
    'composite primary-key first column is mapped' => [static fn () => $columnNames(SQLiteCreateTable::automaticIndexColumnMetadata($compositeSql)[0]), ['blog_id', 'option_name']],
    'composite primary-key collation override is mapped' => [static fn () => $columnCollations(SQLiteCreateTable::automaticIndexColumnMetadata($compositeSql)[0]), ['BINARY', 'BINARY']],
    'composite primary-key desc term is mapped' => [static fn () => $columnDirections(SQLiteCreateTable::automaticIndexColumnMetadata($compositeSql)[0]), [false, true]],
    'unique after composite primary-key is preserved' => [static fn () => $columnNames(SQLiteCreateTable::automaticIndexColumnMetadata($compositeSql)[1]), ['option_name', 'locale']],
    'unique after composite primary-key keeps explicit collation' => [static fn () => $columnCollations(SQLiteCreateTable::automaticIndexColumnMetadata($compositeSql)[1]), ['NOCASE', 'NOCASE']],
    'automatic first columns include composite primary-key and unique' => [static fn () => SQLiteCreateTable::automaticIndexFirstColumns($compositeSql), ['blog_id', 'option_name']],
    'unique-only first columns omit composite primary-key' => [static fn () => SQLiteCreateTable::uniqueAutoIndexFirstColumns($compositeSql), ['option_name']],
    'without-rowid column primary-key is automatic key metadata' => [static fn () => $columnNames(SQLiteCreateTable::automaticIndexColumnMetadata($columnPrimarySql)[0]), ['network_id']],
    'without-rowid column primary-key keeps integer collation' => [static fn () => $columnCollations(SQLiteCreateTable::automaticIndexColumnMetadata($columnPrimarySql)[0]), ['BINARY']],
    'without-rowid column unique follows primary-key metadata' => [static fn () => $columnNames(SQLiteCreateTable::automaticIndexColumnMetadata($columnPrimarySql)[1]), ['option_name']],
    'without-rowid unique-only omits column primary-key' => [static fn () => SQLiteCreateTable::uniqueAutoIndexFirstColumns($columnPrimarySql), ['option_name']],
    'quoted composite primary-key column names are unquoted' => [static fn () => $columnNames(SQLiteCreateTable::automaticIndexColumnMetadata($quotedSql)[0]), ['tenant id', 'option name', 'autoload flag']],
    'quoted composite primary-key inherited collations are mapped' => [static fn () => $columnCollations(SQLiteCreateTable::automaticIndexColumnMetadata($quotedSql)[0]), ['BINARY', 'NOCASE', 'BINARY']],
    'quoted composite primary-key desc term is mapped' => [static fn () => $columnDirections(SQLiteCreateTable::automaticIndexColumnMetadata($quotedSql)[0]), [false, true, false]],
    'comments and string literals do not create fake primary keys' => [static fn () => count(SQLiteCreateTable::automaticIndexColumnMetadata($quotedSql)), 1],
    'rowid table integer primary-key still omits rowid alias' => [static fn () => SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE t(id INTEGER PRIMARY KEY, slug TEXT UNIQUE)'), ['slug']],
    'rowid table integer primary-key desc remains non-rowid key metadata' => [static fn () => SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE t(id INTEGER PRIMARY KEY DESC, slug TEXT UNIQUE)'), ['id', 'slug']],
    'rowid table table-level integer primary-key still omits rowid alias' => [static fn () => SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE t(id INTEGER, slug TEXT UNIQUE, PRIMARY KEY(id))'), ['slug']],
    'without-rowid table-level integer primary-key is not rowid alias' => [static fn () => SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE t(id INTEGER, slug TEXT UNIQUE, PRIMARY KEY(id)) WITHOUT ROWID'), ['slug', 'id']],
] as $name => [$callback, $expected]) {
    $tests['without rowid composite primary key corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

foreach ([
    'table-info pk ordinal blog id' => ['PRAGMA table_info("wp site options")', 'rows.0.pk', 1],
    'table-info pk ordinal option name' => ['PRAGMA table_info("wp site options")', 'rows.1.pk', 2],
    'table-info non-key locale' => ['PRAGMA table_info("wp site options")', 'rows.2.pk', 0],
    'table-info not-null from composite pk' => ['PRAGMA table_info("wp site options")', 'rows.1.notnull', 1],
    'index-info first autoindex composite first term' => ['PRAGMA index_info("sqlite_autoindex_wp site options_1")', 'rows.0.name', 'blog_id'],
    'index-info first autoindex composite second term' => ['PRAGMA index_info("sqlite_autoindex_wp site options_1")', 'rows.1.name', 'option_name'],
    'index-info unique autoindex first term' => ['PRAGMA index_info("sqlite_autoindex_wp site options_2")', 'rows.0.name', 'option_name'],
    'index-info unique autoindex second term' => ['PRAGMA index_info("sqlite_autoindex_wp site options_2")', 'rows.1.name', 'locale'],
    'quoted table-info pk first term' => ['PRAGMA table_info("wp tenant options")', 'rows.0.name', 'tenant id'],
    'quoted table-info pk third ordinal' => ['PRAGMA table_info("wp tenant options")', 'rows.2.pk', 3],
    'quoted index-info third term' => ['PRAGMA index_info("sqlite_autoindex_wp tenant options_1")', 'rows.2.name', 'autoload flag'],
] as $name => [$sql, $path, $expected]) {
    $tests['without rowid composite primary key corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $sql, $path, $expected): void {
        $value = $makeCatalog()->execute($sql);
        foreach (explode('.', $path) as $part) {
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
        }
        $t->same($expected, $value);
    };
}

return $tests;
