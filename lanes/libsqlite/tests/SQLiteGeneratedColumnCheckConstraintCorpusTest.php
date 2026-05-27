<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$schemaSql = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT DEFAULT '',
    autoload TEXT DEFAULT 'yes',
    option_name_fold TEXT AS (
        lower(option_name || ' CHECK UNIQUE PRIMARY KEY ')
    ) VIRTUAL CHECK(option_name_fold <> 'forbidden'),
    option_value_len INTEGER GENERATED ALWAYS AS (
        length(option_value || ' CHECK(option_value) ')
    ) STORED NOT NULL,
    option_bucket TEXT GENERATED ALWAYS AS (
        CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END
    ) VIRTUAL UNIQUE,
    option_json_type TEXT AS (
        json_extract(option_value, '$.type')
    ) STORED,
    CHECK(length(option_name) > 0 AND option_name <> 'UNIQUE'),
    UNIQUE(option_name),
    CONSTRAINT autoload_name_unique UNIQUE(autoload, option_name)
)
SQL;

$catalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, $schemaSql, 1),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_2', 'wp_options', 4, null, 3),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_3', 'wp_options', 5, null, 4),
]);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

foreach ([
    'table-info visible column count' => ['PRAGMA table_info(wp_options)', 'rows.count', 4],
    'table-info keeps option_id visible' => ['PRAGMA table_info(wp_options)', 'rows.0.name', 'option_id'],
    'table-info keeps option_name visible' => ['PRAGMA table_info(wp_options)', 'rows.1.name', 'option_name'],
    'table-info keeps option_value default visible' => ['PRAGMA table_info(wp_options)', 'rows.2.dflt_value', "''"],
    'table-info keeps autoload default visible' => ['PRAGMA table_info(wp_options)', 'rows.3.dflt_value', "'yes'"],
    'table-xinfo all column count' => ['PRAGMA table_xinfo(wp_options)', 'rows.count', 8],
    'shorthand virtual generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.hidden', 2],
    'verbose stored generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.hidden', 3],
    'verbose virtual generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.hidden', 2],
    'shorthand stored generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.hidden', 3],
    'generated shorthand type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.type', 'TEXT'],
    'generated stored type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.type', 'INTEGER'],
    'generated unique type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.type', 'TEXT'],
    'generated json type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.type', 'TEXT'],
    'generated check not-null stays nullable' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.notnull', 0],
    'stored generated not-null recorded' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.notnull', 1],
    'generated unique column named' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.name', 'option_bucket'],
    'generated json column named' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.name', 'option_json_type'],
    'index-list autoindex count' => ['PRAGMA index_list(wp_options)', 'rows.count', 3],
    'index-list first autoindex origin' => ['PRAGMA index_list(wp_options)', 'rows.0.origin', 'u'],
    'index-list second autoindex unique' => ['PRAGMA index_list(wp_options)', 'rows.1.unique', 1],
    'index-list third autoindex partial false' => ['PRAGMA index_list(wp_options)', 'rows.2.partial', 0],
    'autoindex generated unique cid' => ['PRAGMA index_info(sqlite_autoindex_wp_options_1)', 'rows.0.cid', 6],
    'autoindex generated unique name' => ['PRAGMA index_info(sqlite_autoindex_wp_options_1)', 'rows.0.name', 'option_bucket'],
    'autoindex option name cid' => ['PRAGMA index_info(sqlite_autoindex_wp_options_2)', 'rows.0.cid', 1],
    'autoindex option name name' => ['PRAGMA index_info(sqlite_autoindex_wp_options_2)', 'rows.0.name', 'option_name'],
    'autoindex composite first name' => ['PRAGMA index_info(sqlite_autoindex_wp_options_3)', 'rows.0.name', 'autoload'],
    'autoindex composite second name' => ['PRAGMA index_info(sqlite_autoindex_wp_options_3)', 'rows.1.name', 'option_name'],
] as $name => [$sql, $path, $expected]) {
    $tests['generated column check constraint corpus ' . $name] = static function (TestRunner $t) use ($catalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($catalog()->execute($sql), $path));
    };
}

foreach ([
    'generated expression keywords only create declared unique indexes' => [
        $schemaSql,
        [
            ['option_bucket'],
            ['option_name'],
            ['autoload', 'option_name'],
        ],
    ],
    'table check with unique text ignored' => [
        "CREATE TABLE wp_options(option_name TEXT CHECK(option_name <> 'UNIQUE(option_name)'), generated_name TEXT AS (option_name || ' CHECK ') VIRTUAL, autoload TEXT UNIQUE)",
        [
            ['autoload'],
        ],
    ],
    'generated unique after shorthand expression is indexed' => [
        "CREATE TABLE wp_options(option_name TEXT, option_hash TEXT AS (lower(option_name || ' UNIQUE ')) STORED UNIQUE)",
        [
            ['option_hash'],
        ],
    ],
    'generated check expression does not create index' => [
        "CREATE TABLE wp_options(option_name TEXT, option_label TEXT GENERATED ALWAYS AS (option_name || ' PRIMARY KEY ') VIRTUAL CHECK(option_label <> ''), UNIQUE(option_name))",
        [
            ['option_name'],
        ],
    ],
] as $name => [$sql, $expected]) {
    $tests['generated column check constraint corpus autoindex ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $actual = array_map(
            static fn (array $columns): array => array_map(static fn ($column): string => $column->columnName, $columns),
            SQLiteCreateTable::automaticIndexColumnMetadata($sql),
        );

        $t->same($expected, $actual);
    };
}

return $tests;
