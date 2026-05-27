<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
            $record('table', 'sqlite_shadow', 'sqlite_shadow', 12, 'CREATE TABLE sqlite_shadow(name TEXT)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('view', 'wp_temp_options', 'wp_temp_options', null, 'CREATE VIEW wp_temp_options AS SELECT option_name FROM wp_options'),
        ],
    );
    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
    ]);
    $catalog->attach('Archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
        $record('table', 'wp_posts', 'wp_posts', 10, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
    ]);

    return $catalog;
};

$resolved = static fn (SQLiteAttachedSchemaCatalog $catalog, string $name): array => $catalog->resolveTable($name) ?? [];

$tests = [];

foreach ([
    'bare sqlite_schema resolves main schema' => ['sqlite_schema', 'schema', 'main'],
    'bare sqlite_master resolves main schema' => ['sqlite_master', 'schema', 'main'],
    'main sqlite_schema explicit schema' => ['main.sqlite_schema', 'schema', 'main'],
    'main sqlite_master explicit schema' => ['main.sqlite_master', 'schema', 'main'],
    'temp sqlite_schema explicit schema' => ['temp.sqlite_schema', 'schema', 'temp'],
    'temp sqlite_master explicit schema' => ['temp.sqlite_master', 'schema', 'temp'],
    'sqlite_temp_schema resolves temp schema' => ['sqlite_temp_schema', 'schema', 'temp'],
    'sqlite_temp_master resolves temp schema' => ['sqlite_temp_master', 'schema', 'temp'],
    'site sqlite_schema explicit schema' => ['site.sqlite_schema', 'schema', 'site'],
    'site sqlite_master explicit schema' => ['site.sqlite_master', 'schema', 'site'],
    'archive sqlite_schema explicit schema lowercased' => ['archive.sqlite_schema', 'schema', 'archive'],
    'archive sqlite_master explicit schema lowercased' => ['archive.sqlite_master', 'schema', 'archive'],
    'quoted archive schema resolves sqlite_schema' => ['"Archive".sqlite_schema', 'schema', 'archive'],
    'bracket site schema resolves sqlite_master' => ['[site].sqlite_master', 'schema', 'site'],
] as $name => [$objectName, $path, $expected]) {
    $tests['attach schema shadowing current next16 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $resolved, $objectName, $path, $expected): void {
        $actual = $resolved($makeCatalog(), $objectName);
        foreach (explode('.', $path) as $part) {
            $actual = $actual[$part];
        }
        $t->same($expected, $actual);
    };
}

foreach ([
    'bare sqlite_schema record name' => ['sqlite_schema', 'record', 'name', 'sqlite_schema'],
    'bare sqlite_master record canonical name' => ['sqlite_master', 'record', 'name', 'sqlite_schema'],
    'temp schema record canonical name' => ['temp.sqlite_master', 'record', 'name', 'sqlite_schema'],
    'attached schema record canonical name' => ['site.sqlite_schema', 'record', 'name', 'sqlite_schema'],
    'bare sqlite_schema table name' => ['sqlite_schema', 'record', 'tableName', 'sqlite_schema'],
    'temp schema table name' => ['sqlite_temp_schema', 'record', 'tableName', 'sqlite_schema'],
    'attached schema table name' => ['archive.sqlite_master', 'record', 'tableName', 'sqlite_schema'],
    'bare sqlite_schema type' => ['sqlite_schema', 'record', 'type', 'table'],
    'temp sqlite_schema type' => ['temp.sqlite_schema', 'record', 'type', 'table'],
    'site sqlite_schema type' => ['site.sqlite_schema', 'record', 'type', 'table'],
    'bare sqlite_schema root page' => ['sqlite_schema', 'record', 'rootPage', 1],
    'temp sqlite_schema root page' => ['temp.sqlite_schema', 'record', 'rootPage', 1],
    'site sqlite_schema root page' => ['site.sqlite_schema', 'record', 'rootPage', 1],
    'archive sqlite_schema root page' => ['archive.sqlite_schema', 'record', 'rootPage', 1],
] as $name => [$objectName, $container, $property, $expected]) {
    $tests['attach schema shadowing current next16 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $resolved, $objectName, $container, $property, $expected): void {
        $actual = $resolved($makeCatalog(), $objectName);
        $t->same($expected, $actual[$container]->{$property});
    };
}

$tests['attach schema shadowing current next16 bare schema bypasses temp object shadowing'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->same('temp', $catalog->resolveTable('wp_options')['schema']);
    $t->same(4, $catalog->resolveTable('wp_options')['record']->rootPage);
    $t->same('main', $catalog->resolveTable('sqlite_schema')['schema']);
    $t->same(1, $catalog->resolveTable('sqlite_schema')['record']->rootPage);
};

$tests['attach schema shadowing current next16 temp aliases bypass main object shadowing'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->same('main', $catalog->resolveTable('sqlite_shadow')['schema']);
    $t->same('temp', $catalog->resolveTable('sqlite_temp_schema')['schema']);
    $t->same('temp', $catalog->resolveTable('sqlite_temp_master')['schema']);
};

foreach ([
    'ordinary table unqualified still temp first' => ['wp_options', 'schema', 'temp'],
    'ordinary table main qualification still main' => ['main.wp_options', 'schema', 'main'],
    'ordinary attached site qualification still site' => ['site.wp_options', 'schema', 'site'],
    'ordinary attached archive qualification still archive' => ['archive.wp_options', 'schema', 'archive'],
    'ordinary temp view remains temp' => ['wp_temp_options', 'schema', 'temp'],
] as $name => [$objectName, $path, $expected]) {
    $tests['attach schema shadowing current next16 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $resolved, $objectName, $path, $expected): void {
        $actual = $resolved($makeCatalog(), $objectName);
        foreach (explode('.', $path) as $part) {
            $actual = $actual[$part];
        }
        $t->same($expected, $actual);
    };
}

foreach ([
    'database list main remains seq zero' => [0, 'name', 'main'],
    'database list temp remains seq one' => [1, 'name', 'temp'],
    'database list site remains seq two' => [2, 'name', 'site'],
    'database list archive lowercased remains seq three' => [3, 'name', 'archive'],
    'database list archive file preserved' => [3, 'file', '/srv/archive.sqlite'],
] as $name => [$row, $column, $expected]) {
    $tests['attach schema shadowing current next16 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $row, $column, $expected): void {
        $t->same($expected, $makeCatalog()->databaseList()[$row][$column]);
    };
}

$tests['attach schema shadowing current next16 detached schema no longer owns sqlite_schema'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $catalog->detach('site');

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->resolveTable('site.sqlite_schema'));
    $t->same('archive', $catalog->resolveTable('archive.sqlite_schema')['schema']);
    $t->same(2, $catalog->databaseList()[2]['seq']);
};

$tests['attach schema shadowing current next16 missing explicit schema raises for catalog alias'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->resolveTable('missing.sqlite_schema'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->resolveTable('missing.sqlite_master'));
};

$tests['attach schema shadowing current next16 schema table sql shape is canonical'] = static function (TestRunner $t) use ($makeCatalog): void {
    $sql = $makeCatalog()->resolveTable('site.sqlite_schema')['record']->sql;

    $t->same(true, str_contains($sql, 'CREATE TABLE sqlite_schema'));
    $t->same(true, str_contains($sql, 'rootpage int'));
    $t->same(true, str_contains($sql, 'sql text'));
};

$tests['attach schema shadowing current next16 unqualified sqlite_schema ignores attach order'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([$record('table', 'main_only', 'main_only', 2)]);
    $catalog->attach('aaa', '/tmp/aaa.sqlite', [$record('table', 'wp_options', 'wp_options', 3)]);
    $catalog->attach('bbb', '/tmp/bbb.sqlite', [$record('table', 'wp_options', 'wp_options', 4)]);

    $t->same('aaa', $catalog->resolveTable('wp_options')['schema']);
    $t->same('main', $catalog->resolveTable('sqlite_schema')['schema']);
    $t->same('main', $catalog->resolveTable('sqlite_master')['schema']);
};

$tests['attach schema shadowing current next16 unqualified temp aliases ignore attach order'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([$record('table', 'main_only', 'main_only', 2)]);
    $catalog->attach('aaa', '/tmp/aaa.sqlite', [$record('table', 'wp_options', 'wp_options', 3)]);

    $t->same('aaa', $catalog->resolveTable('wp_options')['schema']);
    $t->same('temp', $catalog->resolveTable('sqlite_temp_schema')['schema']);
    $t->same('temp', $catalog->resolveTable('sqlite_temp_master')['schema']);
};

$tests['attach schema shadowing current next16 quoted catalog aliases normalize object case'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->same('main', $catalog->resolveTable('"sqlite_schema"')['schema']);
    $t->same('site', $catalog->resolveTable('site."sqlite_master"')['schema']);
    $t->same('temp', $catalog->resolveTable('"sqlite_temp_schema"')['schema']);
};

return $tests;
