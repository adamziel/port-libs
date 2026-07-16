<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableColumnCorpus;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$table = static fn (string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, $sql, 1);
$base = $table('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0))');

$addCases = [
    'text nullable' => ['ALTER TABLE wp_options ADD COLUMN option_group TEXT', 'option_group', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), option_group TEXT)'],
    'without column keyword' => ['ALTER TABLE wp_options ADD last_changed INTEGER DEFAULT 0', 'last_changed', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), last_changed INTEGER DEFAULT 0)'],
    'schema qualified target' => ['ALTER TABLE main.wp_options ADD COLUMN network_id INTEGER NOT NULL DEFAULT 1', 'network_id', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), network_id INTEGER NOT NULL DEFAULT 1)'],
    'quoted target and column' => ['ALTER TABLE "wp_options" ADD COLUMN "cache key" TEXT DEFAULT ""', 'cache key', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), "cache key" TEXT DEFAULT "")'],
    'generated virtual column' => ['ALTER TABLE wp_options ADD COLUMN name_len INTEGER GENERATED ALWAYS AS (length(option_name)) VIRTUAL', 'name_len', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), name_len INTEGER GENERATED ALWAYS AS (length(option_name)) VIRTUAL)'],
    'references default null accepted' => ['ALTER TABLE wp_options ADD COLUMN site_id INTEGER REFERENCES wp_blogs(blog_id)', 'site_id', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER CHECK(blog_id > 0), site_id INTEGER REFERENCES wp_blogs(blog_id))'],
];

foreach ($addCases as $name => [$sql, $column, $count, $rewritten]) {
    $tests['alter table add column corpus ' . $name . ' status'] = static fn (TestRunner $t) => $t->same('added', SQLiteAlterTableColumnCorpus::addColumn($base, $sql)['status']);
    $tests['alter table add column corpus ' . $name . ' column'] = static fn (TestRunner $t) => $t->same($column, SQLiteAlterTableColumnCorpus::addColumn($base, $sql)['column']);
    $tests['alter table add column corpus ' . $name . ' count'] = static fn (TestRunner $t) => $t->same($count, SQLiteAlterTableColumnCorpus::addColumn($base, $sql)['column_count']);
    $tests['alter table add column corpus ' . $name . ' rewrite'] = static fn (TestRunner $t) => $t->same($rewritten, SQLiteAlterTableColumnCorpus::addColumn($base, $sql)['sql']);
}

$addRejectCases = [
    'duplicate column' => 'ALTER TABLE wp_options ADD COLUMN option_name TEXT',
    'primary key column' => 'ALTER TABLE wp_options ADD COLUMN local_id INTEGER PRIMARY KEY',
    'unique column' => 'ALTER TABLE wp_options ADD COLUMN option_hash TEXT UNIQUE',
    'not null without default' => 'ALTER TABLE wp_options ADD COLUMN required_flag TEXT NOT NULL',
    'current timestamp default' => 'ALTER TABLE wp_options ADD COLUMN touched_at TEXT DEFAULT CURRENT_TIMESTAMP',
    'stored generated column' => 'ALTER TABLE wp_options ADD COLUMN stored_name TEXT GENERATED ALWAYS AS (lower(option_name)) STORED',
    'wrong table' => 'ALTER TABLE wp_posts ADD COLUMN option_group TEXT',
    'malformed add' => 'ALTER TABLE wp_options ADD',
];

foreach ($addRejectCases as $name => $sql) {
    $tests['alter table add column corpus rejects ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::addColumn($base, $sql));
}

$dropBase = $table('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", transient_timeout INTEGER, blog_id INTEGER)');
$dropCases = [
    'drop nullable middle' => ['ALTER TABLE wp_options DROP COLUMN option_value', 'option_value', 5, ['option_id', 'option_name', 'autoload', 'transient_timeout', 'blog_id'], 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, autoload TEXT DEFAULT "yes", transient_timeout INTEGER, blog_id INTEGER)'],
    'drop with column keyword omitted' => ['ALTER TABLE wp_options DROP transient_timeout', 'transient_timeout', 5, ['option_id', 'option_name', 'option_value', 'autoload', 'blog_id'], 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", blog_id INTEGER)'],
    'drop quoted identifier' => ['ALTER TABLE "wp_options" DROP COLUMN "blog_id"', 'blog_id', 5, ['option_id', 'option_name', 'option_value', 'autoload', 'transient_timeout'], 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", transient_timeout INTEGER)'],
    'drop case insensitive' => ['ALTER TABLE WP_OPTIONS DROP COLUMN AUTOLOAD', 'AUTOLOAD', 5, ['option_id', 'option_name', 'option_value', 'transient_timeout', 'blog_id'], 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, transient_timeout INTEGER, blog_id INTEGER)'],
];

foreach ($dropCases as $name => [$sql, $column, $count, $preserved, $rewritten]) {
    $tests['alter table drop column corpus ' . $name . ' status'] = static fn (TestRunner $t) => $t->same('dropped', SQLiteAlterTableColumnCorpus::dropColumn($dropBase, $sql)['status']);
    $tests['alter table drop column corpus ' . $name . ' column'] = static fn (TestRunner $t) => $t->same($column, SQLiteAlterTableColumnCorpus::dropColumn($dropBase, $sql)['column']);
    $tests['alter table drop column corpus ' . $name . ' count'] = static fn (TestRunner $t) => $t->same($count, SQLiteAlterTableColumnCorpus::dropColumn($dropBase, $sql)['column_count']);
    $tests['alter table drop column corpus ' . $name . ' preserved'] = static fn (TestRunner $t) => $t->same($preserved, SQLiteAlterTableColumnCorpus::dropColumn($dropBase, $sql)['preserved']);
    $tests['alter table drop column corpus ' . $name . ' rewrite'] = static fn (TestRunner $t) => $t->same($rewritten, SQLiteAlterTableColumnCorpus::dropColumn($dropBase, $sql)['sql']);
}

$dependentSchema = [
    new SQLiteSchemaRecord('index', 'idx_option_value', 'wp_options', 3, 'CREATE INDEX idx_option_value ON wp_options(option_value)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_option_values', 'autoloaded_option_values', 0, 'CREATE VIEW autoloaded_option_values AS SELECT option_name, option_value FROM wp_options WHERE autoload = "yes"', 3),
    new SQLiteSchemaRecord('trigger', 'option_value_audit', 'wp_options', 0, 'CREATE TRIGGER option_value_audit AFTER UPDATE OF option_value ON wp_options BEGIN SELECT new.option_value; END', 4),
];

$tests['alter table drop column corpus reports dependent index'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_options DROP COLUMN option_value', [$dependentSchema[0]]));
$tests['alter table drop column corpus reports dependent view'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_options DROP COLUMN option_value', [$dependentSchema[1]]));
$tests['alter table drop column corpus reports dependent trigger'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_options DROP COLUMN option_value', [$dependentSchema[2]]));

$constraintTable = $table('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT, CHECK(length(option_value) > 0), UNIQUE(autoload, option_value))');
$tests['alter table drop column corpus rejects primary key column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($constraintTable, 'ALTER TABLE wp_options DROP COLUMN option_id'));
$tests['alter table drop column corpus rejects unique column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($constraintTable, 'ALTER TABLE wp_options DROP COLUMN option_name'));
$tests['alter table drop column corpus rejects check constraint reference'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($constraintTable, 'ALTER TABLE wp_options DROP COLUMN option_value'));
$tests['alter table drop column corpus rejects table unique reference'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($constraintTable, 'ALTER TABLE wp_options DROP COLUMN autoload'));
$tests['alter table drop column corpus rejects missing column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_options DROP COLUMN missing_column'));
$tests['alter table drop column corpus rejects wrong table'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_posts DROP COLUMN option_value'));
$tests['alter table drop column corpus rejects malformed sql'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::dropColumn($dropBase, 'ALTER TABLE wp_options DROP'));

$tests['alter table column corpus extracts base table columns'] = static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload', 'blog_id'], SQLiteAlterTableColumnCorpus::tableColumns($base->sql));
$tests['alter table column corpus extracts quoted columns'] = static fn (TestRunner $t) => $t->same(['option id', 'option name', 'value'], SQLiteAlterTableColumnCorpus::tableColumns('CREATE TABLE wp_options("option id" INTEGER, [option name] TEXT, `value` TEXT DEFAULT "a,b")'));
$tests['alter table column corpus skips table constraints'] = static fn (TestRunner $t) => $t->same(['a', 'b'], SQLiteAlterTableColumnCorpus::tableColumns('CREATE TABLE t(a INTEGER, b TEXT, UNIQUE(a,b), CHECK(length(b)>0))'));
$tests['alter table column corpus rejects malformed create table'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::tableColumns('CREATE TABLE wp_options'));

return $tests;
