<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaCatalogDdlPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$baseRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id INTEGER, label TEXT, option_name TEXT)', 2),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name FROM main.wp_options WHERE autoload = 'yes'", 3),
    $record('trigger', 'autoloaded_options_insert', 'autoloaded_options', 0, "CREATE TRIGGER main.autoloaded_options_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); END", 4),
];

$ddl = <<<'SQL'
DROP TRIGGER IF EXISTS main.autoloaded_options_insert;
DROP VIEW IF EXISTS main.autoloaded_options;
CREATE TEMP VIEW IF NOT EXISTS temp.autoloaded_options AS
  SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload = 'yes';
CREATE TEMP TRIGGER IF NOT EXISTS temp.autoloaded_options_insert
INSTEAD OF INSERT ON main.autoloaded_options
BEGIN
  INSERT INTO main.wp_options(option_id, option_name, option_value, autoload)
  VALUES(new.option_id, new.option_name, new.option_value, 'yes');
  INSERT INTO main.wp_option_audit(option_id, label, option_name)
  VALUES(new.option_id, 'view insert; schema qualified', new.option_name);
END;
CREATE VIEW main.active_plugin_options AS
  SELECT option_name FROM main.wp_options WHERE option_name GLOB 'active_*';
CREATE TRIGGER main.active_plugin_options_delete
INSTEAD OF DELETE ON main.active_plugin_options
BEGIN
  UPDATE main.wp_options SET autoload = 'no' WHERE option_name = old.option_name;
END;
SQL;

$plan = static fn (array $records = null, string $sql = null, array $options = []): array => SQLiteSchemaCatalogDdlPlan::currentNext(
    $records ?? $baseRecords(),
    $sql ?? $ddl,
    array_replace(['schema_version' => 73, 'data_version' => 11, 'next_rootpage' => 20, 'next_rowid' => 20], $options)
);

$byName = static function (array $rows, string $name): ?array {
    foreach ($rows as $row) {
        if ($row['name'] === $name) {
            return $row;
        }
    }

    return null;
};

$cases = [
    'status ok' => static fn (): mixed => $plan()['status'],
    'operation current next' => static fn (): mixed => $plan()['operation'],
    'statement count includes trigger bodies as one statement each' => static fn (): mixed => $plan()['statement_count'],
    'applied count includes drops and creates' => static fn (): mixed => $plan()['applied_count'],
    'skipped count zero' => static fn (): mixed => $plan()['skipped_count'],
    'warnings zero' => static fn (): mixed => count($plan()['warnings']),
    'schema before preserved' => static fn (): mixed => $plan()['schema_version_before'],
    'schema after advances per applied schema statement' => static fn (): mixed => $plan()['schema_version_after'],
    'data version before preserved' => static fn (): mixed => $plan()['data_version_before'],
    'data version after advances once' => static fn (): mixed => $plan()['data_version_after'],
    'current row count' => static fn (): mixed => count($plan()['current']),
    'next row count' => static fn (): mixed => count($plan()['next']),
    'dropped trigger normalized without schema' => static fn (): mixed => $plan()['dropped'][0],
    'dropped view normalized without schema' => static fn (): mixed => $plan()['dropped'][1],
    'old trigger sql replaced by recreated temp trigger' => static fn (): mixed => str_contains($byName($plan()['next'], 'autoloaded_options_insert')['sql'], 'view insert; schema qualified'),
    'old view recreated as temp name' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options')['type'],
    'temp view rootpage zero' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options')['rootpage'],
    'temp view rowid assigned first' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options')['rowid'],
    'temp view sql keeps qualified text' => static fn (): mixed => str_contains($byName($plan()['next'], 'autoloaded_options')['sql'], 'main.wp_options'),
    'temp trigger exists' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options_insert')['type'],
    'temp trigger target strips main qualifier' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options_insert')['tbl_name'],
    'temp trigger rootpage zero' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options_insert')['rootpage'],
    'temp trigger rowid assigned after view' => static fn (): mixed => $byName($plan()['next'], 'autoloaded_options_insert')['rowid'],
    'temp trigger body semicolon stays intact' => static fn (): mixed => str_contains($byName($plan()['next'], 'autoloaded_options_insert')['sql'], 'view insert; schema qualified'),
    'main view exists' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options')['type'],
    'main view rootpage zero' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options')['rootpage'],
    'main view rowid assigned after trigger' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options')['rowid'],
    'main trigger exists' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options_delete')['type'],
    'main trigger target strips schema-qualified view' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options_delete')['tbl_name'],
    'main trigger rowid assigned last' => static fn (): mixed => $byName($plan()['next'], 'active_plugin_options_delete')['rowid'],
    'first applied drop trigger' => static fn (): mixed => $plan()['applied'][0]['type'],
    'second applied drop view' => static fn (): mixed => $plan()['applied'][1]['type'],
    'third applied create temp view' => static fn (): mixed => $plan()['applied'][2]['type'],
    'fourth applied create temp trigger' => static fn (): mixed => $plan()['applied'][3]['type'],
    'trigger create dependency names catalog' => static fn (): mixed => in_array('sqlite-schema-catalog-ddl', $plan()['applied'][3]['dependencies'], true),
    'view create dependency names catalog' => static fn (): mixed => in_array('sqlite-schema-catalog-ddl', $plan()['applied'][4]['dependencies'], true),
    'plan dependency names cookie update' => static fn (): mixed => in_array('sqlite-schema-cookie-update', $plan()['dependencies'], true),
    'drop temp trigger with quoted schema normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('trigger', 'wp trg', 'wp_options', 0, 'CREATE TRIGGER temp."wp trg" AFTER INSERT ON main.wp_options BEGIN SELECT 1; END', 1)], 'DROP TRIGGER temp."wp trg";')['dropped'][0],
    'drop main view with bracket name normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('view', 'wp view', 'wp view', 0, 'CREATE VIEW main.[wp view] AS SELECT 1', 1)], 'DROP VIEW main.[wp view];')['dropped'][0],
    'create temp view existing if not exists skips' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW temp.autoloaded_options AS SELECT 1', 1)], 'CREATE TEMP VIEW IF NOT EXISTS temp.autoloaded_options AS SELECT 2;', ['schema_version' => 5])['skipped'][0]['reason'],
    'create temp view existing keeps schema version' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW temp.autoloaded_options AS SELECT 1', 1)], 'CREATE TEMP VIEW IF NOT EXISTS temp.autoloaded_options AS SELECT 2;', ['schema_version' => 5])['schema_version_after'],
    'create temp trigger existing if not exists skips' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('trigger', 'autoloaded_options_insert', 'autoloaded_options', 0, 'CREATE TRIGGER temp.autoloaded_options_insert INSTEAD OF INSERT ON main.autoloaded_options BEGIN SELECT 1; END', 1)], 'CREATE TEMP TRIGGER IF NOT EXISTS temp.autoloaded_options_insert INSTEAD OF INSERT ON main.autoloaded_options BEGIN SELECT 2; END;')['skipped'][0]['reason'],
    'create trigger on quoted qualified table captures table' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE TRIGGER main."wp trg" AFTER INSERT ON main."wp options" BEGIN SELECT 1; END;')['next'][0]['tbl_name'],
    'create trigger on bracket qualified view captures view' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE TEMP TRIGGER temp.[wp trg] INSTEAD OF DELETE ON main.[wp view] BEGIN SELECT old.option_name; END;')['next'][0]['tbl_name'],
    'drop qualified missing if exists skips' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'DROP VIEW IF EXISTS main.missing_view;')['skipped'][0]['reason'],
    'drop qualified missing if exists keeps data version' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'DROP VIEW IF EXISTS main.missing_view;', ['data_version' => 9])['data_version_after'],
    'qualified create table name normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE TABLE main.wp_options(id INTEGER);')['next'][0]['name'],
    'qualified create index name normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(id INTEGER)', 1)], 'CREATE INDEX main.wp_options_name ON main.wp_options(id);')['next'][1]['name'],
    'qualified create index table normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(id INTEGER)', 1)], 'CREATE INDEX main.wp_options_name ON main.wp_options(id);')['next'][1]['tbl_name'],
    'qualified alter table name normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(id INTEGER)', 1)], 'ALTER TABLE main.wp_options RENAME TO wp_options_archive;')['renamed'][0]['from'],
    'qualified alter table rewrite target' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(id INTEGER)', 1)], 'ALTER TABLE main.wp_options RENAME TO wp_options_archive;')['next'][0]['name'],
    'schema qualified drop wrong type rejects' => static function () use ($record): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([$record('view', 'wp_view', 'wp_view', 0, 'CREATE VIEW main.wp_view AS SELECT 1', 1)], 'DROP TRIGGER main.wp_view;');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'too many schema qualifiers warn as unsupported' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE VIEW main.temp.wp_view AS SELECT 1;')['warnings'][0]['reason'],
    'trigger semicolon body statement count remains one' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], "CREATE TRIGGER main.wp_trg AFTER INSERT ON main.wp_options BEGIN SELECT 'a;b'; SELECT 2; END;")['statement_count'],
    'trigger semicolon body stored intact' => static fn (): mixed => str_contains(SQLiteSchemaCatalogDdlPlan::currentNext([], "CREATE TRIGGER main.wp_trg AFTER INSERT ON main.wp_options BEGIN SELECT 'a;b'; SELECT 2; END;")['next'][0]['sql'], "'a;b'"),
];

$expected = [
    'status ok' => 'ok',
    'operation current next' => 'schema-catalog-ddl-current-next',
    'statement count includes trigger bodies as one statement each' => 6,
    'applied count includes drops and creates' => 6,
    'skipped count zero' => 0,
    'warnings zero' => 0,
    'schema before preserved' => 73,
    'schema after advances per applied schema statement' => 79,
    'data version before preserved' => 11,
    'data version after advances once' => 12,
    'current row count' => 4,
    'next row count' => 6,
    'dropped trigger normalized without schema' => 'autoloaded_options_insert',
    'dropped view normalized without schema' => 'autoloaded_options',
    'old trigger sql replaced by recreated temp trigger' => true,
    'old view recreated as temp name' => 'view',
    'temp view rootpage zero' => 0,
    'temp view rowid assigned first' => 20,
    'temp view sql keeps qualified text' => true,
    'temp trigger exists' => 'trigger',
    'temp trigger target strips main qualifier' => 'autoloaded_options',
    'temp trigger rootpage zero' => 0,
    'temp trigger rowid assigned after view' => 21,
    'temp trigger body semicolon stays intact' => true,
    'main view exists' => 'view',
    'main view rootpage zero' => 0,
    'main view rowid assigned after trigger' => 22,
    'main trigger exists' => 'trigger',
    'main trigger target strips schema-qualified view' => 'active_plugin_options',
    'main trigger rowid assigned last' => 23,
    'first applied drop trigger' => 'trigger',
    'second applied drop view' => 'view',
    'third applied create temp view' => 'view',
    'fourth applied create temp trigger' => 'trigger',
    'trigger create dependency names catalog' => true,
    'view create dependency names catalog' => true,
    'plan dependency names cookie update' => true,
    'drop temp trigger with quoted schema normalizes' => 'wp trg',
    'drop main view with bracket name normalizes' => 'wp view',
    'create temp view existing if not exists skips' => 'already_exists_if_not_exists',
    'create temp view existing keeps schema version' => 5,
    'create temp trigger existing if not exists skips' => 'already_exists_if_not_exists',
    'create trigger on quoted qualified table captures table' => 'wp options',
    'create trigger on bracket qualified view captures view' => 'wp view',
    'drop qualified missing if exists skips' => 'missing_if_exists',
    'drop qualified missing if exists keeps data version' => 9,
    'qualified create table name normalizes' => 'wp_options',
    'qualified create index name normalizes' => 'wp_options_name',
    'qualified create index table normalizes' => 'wp_options',
    'qualified alter table name normalizes' => 'wp_options',
    'qualified alter table rewrite target' => 'wp_options_archive',
    'schema qualified drop wrong type rejects' => 'rejected',
    'too many schema qualifiers warn as unsupported' => 'unsupported_or_non_schema_statement',
    'trigger semicolon body statement count remains one' => 1,
    'trigger semicolon body stored intact' => true,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite schema trigger view current next73 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
