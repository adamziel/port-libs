<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaCatalogDdlPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$baseRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    $record('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes'", 4),
    $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_log(message) VALUES('created; option'); END", 5),
];

$ddl = <<<'SQL'
-- Application import DDL after a migration plugin rewrites the options schema.
DROP VIEW IF EXISTS wp_autoloaded_options;
DROP INDEX wp_options_autoload;
ALTER TABLE wp_options RENAME TO wp_options_archive;
CREATE TABLE IF NOT EXISTS wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL UNIQUE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes'
);
CREATE INDEX wp_options_autoload ON wp_options(autoload, option_name);
CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes';
CREATE TRIGGER wp_options_ai_new AFTER INSERT ON wp_options BEGIN
  INSERT INTO wp_log(message) VALUES('created; current');
END;
INSERT INTO wp_options(option_name, option_value) VALUES('siteurl', 'https://example.test');
SQL;

$plan = static fn (array $records = null, string $sql = null, array $options = []): array => SQLiteSchemaCatalogDdlPlan::currentNext(
    $records ?? $baseRecords(),
    $sql ?? $ddl,
    array_replace(['schema_version' => 20, 'data_version' => 9, 'next_rootpage' => 10, 'next_rowid' => 10], $options)
);

$byName = static function (array $rows, string $name): ?array {
    foreach ($rows as $row) {
        if ($row['name'] === $name) {
            return $row;
        }
    }

    return null;
};

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status ok' => static fn (): mixed => $plan()['status'],
    'operation names current next catalog' => static fn (): mixed => $plan()['operation'],
    'statement count includes unsupported insert' => static fn (): mixed => $plan()['statement_count'],
    'applied count excludes unsupported insert' => static fn (): mixed => $plan()['applied_count'],
    'skipped count is zero' => static fn (): mixed => $plan()['skipped_count'],
    'one warning recorded' => static fn (): mixed => count($plan()['warnings']),
    'warning reason names unsupported statement' => static fn (): mixed => $plan()['warnings'][0]['reason'],
    'schema version before preserved' => static fn (): mixed => $plan()['schema_version_before'],
    'schema version advances per applied ddl' => static fn (): mixed => $plan()['schema_version_after'],
    'data version before preserved' => static fn (): mixed => $plan()['data_version_before'],
    'data version advances once' => static fn (): mixed => $plan()['data_version_after'],
    'current row count preserved' => static fn (): mixed => count($plan()['current']),
    'next row count includes renamed and recreated objects' => static fn (): mixed => count($plan()['next']),
    'dropped view is reported first' => static fn (): mixed => $plan()['dropped'][0],
    'dropped index is reported second' => static fn (): mixed => $plan()['dropped'][1],
    'rename from captured' => static fn (): mixed => $plan()['renamed'][0]['from'],
    'rename to captured' => static fn (): mixed => $plan()['renamed'][0]['to'],
    'rename type captured' => static fn (): mixed => $plan()['renamed'][0]['type'],
    'archived table keeps original rootpage' => static fn (): mixed => $byName($plan()['next'], 'wp_options_archive')['rootpage'],
    'archived table keeps original rowid' => static fn (): mixed => $byName($plan()['next'], 'wp_options_archive')['rowid'],
    'archived table tbl name rewritten' => static fn (): mixed => $byName($plan()['next'], 'wp_options_archive')['tbl_name'],
    'archived table sql rewritten with quotes' => static fn (): mixed => str_starts_with($byName($plan()['next'], 'wp_options_archive')['sql'], 'CREATE TABLE "wp_options_archive"'),
    'autoindex follows renamed table' => static fn (): mixed => $byName($plan()['next'], 'sqlite_autoindex_wp_options_1')['tbl_name'],
    'autoindex rootpage preserved' => static fn (): mixed => $byName($plan()['next'], 'sqlite_autoindex_wp_options_1')['rootpage'],
    'old explicit index removed' => static fn (): mixed => $byName($plan()['next'], 'wp_options_autoload_old') === null,
    'new table exists' => static fn (): mixed => $byName($plan()['next'], 'wp_options')['type'],
    'new table rootpage assigned' => static fn (): mixed => $byName($plan()['next'], 'wp_options')['rootpage'],
    'new table rowid assigned' => static fn (): mixed => $byName($plan()['next'], 'wp_options')['rowid'],
    'new index exists' => static fn (): mixed => $byName($plan()['next'], 'wp_options_autoload')['type'],
    'new index tbl name captured' => static fn (): mixed => $byName($plan()['next'], 'wp_options_autoload')['tbl_name'],
    'new index rootpage assigned after table' => static fn (): mixed => $byName($plan()['next'], 'wp_options_autoload')['rootpage'],
    'new index rowid assigned after table' => static fn (): mixed => $byName($plan()['next'], 'wp_options_autoload')['rowid'],
    'new view has rootpage zero' => static fn (): mixed => $byName($plan()['next'], 'wp_autoloaded_options')['rootpage'],
    'new trigger has rootpage zero' => static fn (): mixed => $byName($plan()['next'], 'wp_options_ai_new')['rootpage'],
    'new trigger table captured' => static fn (): mixed => $byName($plan()['next'], 'wp_options_ai_new')['tbl_name'],
    'trigger semicolon body stays intact' => static fn (): mixed => str_contains($byName($plan()['next'], 'wp_options_ai_new')['sql'], 'created; current'),
    'applied first action is drop' => static fn (): mixed => $plan()['applied'][0]['action'],
    'applied third action is rename table' => static fn (): mixed => $plan()['applied'][2]['action'],
    'applied create table dependency includes rootpage' => static fn (): mixed => in_array('sqlite-schema-record-table-rootpage', $plan()['applied'][3]['dependencies'], true),
    'applied create index dependency includes rootpage' => static fn (): mixed => in_array('sqlite-schema-record-index-rootpage', $plan()['applied'][4]['dependencies'], true),
    'plan dependency names ddl catalog' => static fn (): mixed => in_array('sqlite-schema-catalog-ddl', $plan()['dependencies'], true),
    'plan dependency names schema cookie' => static fn (): mixed => in_array('sqlite-schema-cookie-update', $plan()['dependencies'], true),
    'plan dependency names rename reparse' => static fn (): mixed => in_array('sqlite-alter-table-rename-reparse', $plan()['dependencies'], true),
    'drop missing if exists skips' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'DROP TABLE IF EXISTS missing;')['skipped'][0]['reason'],
    'drop missing if exists leaves schema version stable' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'DROP TABLE IF EXISTS missing;', ['schema_version' => 7])['schema_version_after'],
    'drop missing without if exists rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], 'DROP TABLE missing;');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'create existing if not exists skips' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);')['skipped'][0]['reason'],
    'create existing if not exists leaves schema stable' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);', ['schema_version' => 7])['schema_version_after'],
    'create existing without if not exists rejects' => static function () use ($baseRecords): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'CREATE TABLE wp_options(id INTEGER);');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'drop wrong object type rejects' => static function () use ($baseRecords): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'DROP TABLE wp_options_autoload;');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'rename missing table rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], 'ALTER TABLE missing RENAME TO other;');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'rename target existing rejects' => static function () use ($baseRecords): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'ALTER TABLE wp_options RENAME TO wp_autoloaded_options;');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'quoted create table name normalized' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE TABLE "wp options"(id INTEGER);')['next'][0]['name'],
    'bracket create index name normalized' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(id INTEGER)', 1)], 'CREATE INDEX [idx options] ON wp_options(id);')['next'][1]['name'],
    'backtick view name normalized' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE VIEW `wp view` AS SELECT 1;')['next'][0]['name'],
    'drop quoted name normalizes' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([$record('view', 'wp view', 'wp view', 0, 'CREATE VIEW "wp view" AS SELECT 1', 1)], 'DROP VIEW "wp view";')['dropped'][0],
    'next rootpage defaults after max root' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'CREATE TABLE wp_new(id INTEGER);')['next'][5]['rootpage'],
    'next rowid defaults after max rowid' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords(), 'CREATE VIEW wp_new AS SELECT 1;')['next'][5]['rowid'],
    'unsupported only keeps data version stable' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'INSERT INTO wp_options VALUES(1);', ['data_version' => 8])['data_version_after'],
    'unsupported only keeps schema version stable' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], 'INSERT INTO wp_options VALUES(1);', ['schema_version' => 8])['schema_version_after'],
    'line comment does not split semicolon' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], "-- ignored;\nCREATE TABLE wp_options(id INTEGER);")['statement_count'],
    'block comment does not split semicolon' => static fn (): mixed => SQLiteSchemaCatalogDdlPlan::currentNext([], "/* ignored; */ CREATE TABLE wp_options(id INTEGER);")['statement_count'],
    'unterminated string rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], "CREATE TABLE wp_options(name TEXT DEFAULT 'bad);");
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'unterminated bracket rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], 'CREATE TABLE [bad(id INTEGER);');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'negative schema version rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], '', ['schema_version' => -1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'non integer next rowid rejects' => static function (): mixed {
        try {
            SQLiteSchemaCatalogDdlPlan::currentNext([], '', ['next_rowid' => 'x']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'status ok' => 'ok',
    'operation names current next catalog' => 'schema-catalog-ddl-current-next',
    'statement count includes unsupported insert' => 8,
    'applied count excludes unsupported insert' => 7,
    'skipped count is zero' => 0,
    'one warning recorded' => 1,
    'warning reason names unsupported statement' => 'unsupported_or_non_schema_statement',
    'schema version before preserved' => 20,
    'schema version advances per applied ddl' => 27,
    'data version before preserved' => 9,
    'data version advances once' => 10,
    'current row count preserved' => 5,
    'next row count includes renamed and recreated objects' => 7,
    'dropped view is reported first' => 'wp_autoloaded_options',
    'dropped index is reported second' => 'wp_options_autoload',
    'rename from captured' => 'wp_options',
    'rename to captured' => 'wp_options_archive',
    'rename type captured' => 'table',
    'archived table keeps original rootpage' => 2,
    'archived table keeps original rowid' => 1,
    'archived table tbl name rewritten' => 'wp_options_archive',
    'archived table sql rewritten with quotes' => true,
    'autoindex follows renamed table' => 'wp_options_archive',
    'autoindex rootpage preserved' => 3,
    'old explicit index removed' => true,
    'new table exists' => 'table',
    'new table rootpage assigned' => 10,
    'new table rowid assigned' => 10,
    'new index exists' => 'index',
    'new index tbl name captured' => 'wp_options',
    'new index rootpage assigned after table' => 11,
    'new index rowid assigned after table' => 11,
    'new view has rootpage zero' => 0,
    'new trigger has rootpage zero' => 0,
    'new trigger table captured' => 'wp_options',
    'trigger semicolon body stays intact' => true,
    'applied first action is drop' => 'drop',
    'applied third action is rename table' => 'rename_table',
    'applied create table dependency includes rootpage' => true,
    'applied create index dependency includes rootpage' => true,
    'plan dependency names ddl catalog' => true,
    'plan dependency names schema cookie' => true,
    'plan dependency names rename reparse' => true,
    'drop missing if exists skips' => 'missing_if_exists',
    'drop missing if exists leaves schema version stable' => 7,
    'drop missing without if exists rejects' => 'rejected',
    'create existing if not exists skips' => 'already_exists_if_not_exists',
    'create existing if not exists leaves schema stable' => 7,
    'create existing without if not exists rejects' => 'rejected',
    'drop wrong object type rejects' => 'rejected',
    'rename missing table rejects' => 'rejected',
    'rename target existing rejects' => 'rejected',
    'quoted create table name normalized' => 'wp options',
    'bracket create index name normalized' => 'idx options',
    'backtick view name normalized' => 'wp view',
    'drop quoted name normalizes' => 'wp view',
    'next rootpage defaults after max root' => 5,
    'next rowid defaults after max rowid' => 6,
    'unsupported only keeps data version stable' => 8,
    'unsupported only keeps schema version stable' => 8,
    'line comment does not split semicolon' => 1,
    'block comment does not split semicolon' => 1,
    'unterminated string rejects' => 'rejected',
    'unterminated bracket rejects' => 'rejected',
    'negative schema version rejects' => 'rejected',
    'non integer next rowid rejects' => 'rejected',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite schema catalog ddl current next56 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
