<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$recordNames = static fn (array $records): array => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records);
$recordTypes = static fn (array $records): array => array_map(static fn (SQLiteSchemaRecord $record): string => $record->type, $records);
$recordByName = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing schema record {$name}");
};

$applicationSchema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL UNIQUE,
    option_value TEXT NOT NULL DEFAULT '',
    autoload TEXT NOT NULL DEFAULT 'yes',
    CHECK(autoload IN ('yes','no'))
);
CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name);
CREATE UNIQUE INDEX wp_options_name_value ON wp_options(option_name, option_value);
CREATE TEMP TABLE IF NOT EXISTS wp_options_stage(
    option_name TEXT PRIMARY KEY,
    option_value TEXT,
    source TEXT UNIQUE
);
CREATE INDEX IF NOT EXISTS temp.wp_options_stage_source ON wp_options_stage(source);
CREATE TABLE archive.wp_options_archive(
    option_id INTEGER,
    option_name TEXT,
    option_value TEXT,
    PRIMARY KEY(option_id, option_name),
    UNIQUE(option_name)
);
SQL;

return [
    'application schema import executes all statements' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $result = $executor->executeScript($applicationSchema, 'main');
        $t->same('ok', $result['status']);
        $t->same(6, count($result['statements']));
    },
    'main schema receives wp_options table and explicit indexes' => static function (TestRunner $t) use ($applicationSchema, $recordNames, $recordTypes): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $records = $executor->schemaRecords('main');
        $t->same(['wp_options', 'sqlite_autoindex_wp_options_1', 'wp_options_autoload_name', 'wp_options_name_value'], $recordNames($records));
        $t->same(['table', 'index', 'index', 'index'], $recordTypes($records));
    },
    'main table record preserves original create sql' => static function (TestRunner $t) use ($applicationSchema, $recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $table = $recordByName($executor->schemaRecords('main'), 'wp_options');
        $t->same('table', $table->type);
        $t->same('wp_options', $table->tableName);
        $t->same(true, str_contains($table->sql ?? '', 'CHECK(autoload IN'));
    },
    'main autoindexes are generated for inline unique constraints without rowid primary key alias' => static function (TestRunner $t) use ($applicationSchema, $recordByName, $recordNames): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $records = $executor->schemaRecords('main');
        $unique = $recordByName($records, 'sqlite_autoindex_wp_options_1');
        $t->same('index', $unique->type);
        $t->same('wp_options', $unique->tableName);
        $t->same(null, $unique->sql);
        $t->same(false, in_array('sqlite_autoindex_wp_options_2', $recordNames($records), true));
    },
    'explicit indexes preserve target table and uniqueness' => static function (TestRunner $t) use ($applicationSchema, $recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $plans = $executor->executeScript($applicationSchema, 'main')['statements'];
        $plain = $recordByName($executor->schemaRecords('main'), 'wp_options_autoload_name');
        $unique = $recordByName($executor->schemaRecords('main'), 'wp_options_name_value');
        $t->same('wp_options', $plain->tableName);
        $t->same(false, $plans[1]['unique']);
        $t->same('wp_options', $unique->tableName);
        $t->same(true, $plans[2]['unique']);
        $t->same(true, str_starts_with($unique->sql ?? '', 'CREATE UNIQUE INDEX'));
    },
    'root pages are allocated monotonically per schema' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $rootPages = array_map(static fn (SQLiteSchemaRecord $record): ?int => $record->rootPage, $executor->schemaRecords('main'));
        $t->same([2, 3, 4, 5], $rootPages);
    },
    'rowids are allocated monotonically per schema' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $rowIds = array_map(static fn (SQLiteSchemaRecord $record): int => $record->rowId, $executor->schemaRecords('main'));
        $t->same([1, 2, 3, 4], $rowIds);
    },
    'temp schema receives temporary staging table and index' => static function (TestRunner $t) use ($applicationSchema, $recordNames): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $t->same(['wp_options_stage', 'sqlite_autoindex_wp_options_stage_1', 'sqlite_autoindex_wp_options_stage_2', 'wp_options_stage_source'], $recordNames($executor->schemaRecords('temp')));
    },
    'temp schema root pages are independent from main' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $rootPages = array_map(static fn (SQLiteSchemaRecord $record): ?int => $record->rootPage, $executor->schemaRecords('temp'));
        $t->same([2, 3, 4, 5], $rootPages);
    },
    'attached schema is created on demand for qualified table' => static function (TestRunner $t) use ($applicationSchema, $recordNames): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $t->same(['wp_options_archive', 'sqlite_autoindex_wp_options_archive_1', 'sqlite_autoindex_wp_options_archive_2'], $recordNames($executor->schemaRecords('archive')));
    },
    'attached table constraints create independent autoindexes' => static function (TestRunner $t) use ($applicationSchema, $recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $records = $executor->schemaRecords('archive');
        $t->same('wp_options_archive', $recordByName($records, 'sqlite_autoindex_wp_options_archive_1')->tableName);
        $t->same('wp_options_archive', $recordByName($records, 'sqlite_autoindex_wp_options_archive_2')->tableName);
    },
    'if not exists table is a no-op for existing import table' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $first = $executor->execute('CREATE TABLE IF NOT EXISTS wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)');
        $second = $executor->execute('CREATE TABLE IF NOT EXISTS wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)');
        $t->same(true, $first['created']);
        $t->same(false, $second['created']);
        $t->same(2, count($executor->schemaRecords('main')));
    },
    'if not exists index is a no-op for existing import index' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)');
        $first = $executor->execute('CREATE INDEX IF NOT EXISTS wp_terms_slug ON wp_terms(slug)');
        $second = $executor->execute('CREATE INDEX IF NOT EXISTS wp_terms_slug ON wp_terms(slug)');
        $t->same(true, $first['created']);
        $t->same(false, $second['created']);
        $t->same(2, count($executor->schemaRecords('main')));
    },
    'current schema routes unqualified imported objects' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE wp_blogmeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT UNIQUE)', 'site2');
        $executor->execute('CREATE INDEX wp_blogmeta_key ON wp_blogmeta(meta_key)', 'site2');
        $t->same([], $executor->schemaRecords('main'));
        $t->same(['wp_blogmeta', 'sqlite_autoindex_wp_blogmeta_1', 'wp_blogmeta_key'], array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $executor->schemaRecords('site2')));
    },
    'qualified index must stay in target table schema' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE archive.wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT)', 'main');
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX main.wp_posts_name ON archive.wp_posts(post_name)', 'main'));
    },
    'index target table must exist in selected schema' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX wp_missing_name ON wp_missing(name)', 'main'));
    },
    'duplicate table without if not exists is rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)');
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'));
    },
    'duplicate index without if not exists is rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)');
        $executor->execute('CREATE INDEX wp_terms_slug ON wp_terms(slug)');
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX wp_terms_slug ON wp_terms(slug)'));
    },
    'reserved sqlite table names are rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE TABLE sqlite_private(id INTEGER)'));
    },
    'reserved sqlite index names are rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)');
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX sqlite_private_idx ON wp_terms(slug)'));
    },
    'unsupported drop statement is rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('DROP TABLE wp_options'));
    },
    'malformed create table is rejected' => static function (TestRunner $t): void {
        $executor = new SQLiteSchemaImportExecutor();
        $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY'));
    },
    'script splitter preserves semicolons inside defaults and checks' => static function (TestRunner $t) use ($recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript("CREATE TABLE wp_messages(id INTEGER PRIMARY KEY, body TEXT DEFAULT 'a;b', CHECK(body <> ';')); CREATE INDEX wp_messages_body ON wp_messages(body);");
        $table = $recordByName($executor->schemaRecords('main'), 'wp_messages');
        $t->same(true, str_contains($table->sql ?? '', "DEFAULT 'a;b'"));
        $t->same(2, count($executor->schemaRecords('main')));
    },
    'quoted identifiers are unquoted in schema records' => static function (TestRunner $t) use ($recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE "wp options"(id INTEGER PRIMARY KEY, "option name" TEXT UNIQUE)');
        $executor->execute('CREATE INDEX "wp options name" ON "wp options"("option name")');
        $records = $executor->schemaRecords('main');
        $t->same(true, in_array('wp options', array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records), true));
        $t->same('wp options', $recordByName($records, 'wp options name')->tableName);
    },
    'catalog handoff exposes imported table info' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $pragma = $executor->catalog()->executeSchemaPragma('PRAGMA table_info(wp_options)');
        $t->same('main', $pragma['schema']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload'], array_column($pragma['rows'], 'name'));
    },
    'catalog handoff exposes temp table shadowing' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $pragma = $executor->catalog()->executeSchemaPragma('PRAGMA table_info(wp_options_stage)');
        $t->same('temp', $pragma['schema']);
        $t->same(['option_name', 'option_value', 'source'], array_column($pragma['rows'], 'name'));
    },
    'catalog handoff exposes explicit indexes' => static function (TestRunner $t) use ($applicationSchema): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->executeScript($applicationSchema, 'main');
        $pragma = $executor->catalog()->executeSchemaPragma('PRAGMA index_list(wp_options)');
        $t->same('main', $pragma['schema']);
        $t->same(true, in_array('wp_options_autoload_name', array_column($pragma['rows'], 'name'), true));
        $t->same(true, in_array('wp_options_name_value', array_column($pragma['rows'], 'name'), true));
    },
    'constructor continues root pages after seeded schema records' => static function (TestRunner $t): void {
        $seed = new SQLiteSchemaRecord('table', 'wp_seed', 'wp_seed', 9, 'CREATE TABLE wp_seed(id INTEGER)', 4);
        $executor = new SQLiteSchemaImportExecutor(['main' => [$seed]]);
        $plan = $executor->execute('CREATE TABLE wp_next(id INTEGER PRIMARY KEY)');
        $t->same(10, $plan['rootpage']);
        $t->same([4, 5], array_map(static fn (SQLiteSchemaRecord $record): int => $record->rowId, $executor->schemaRecords('main')));
    },
];
