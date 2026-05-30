<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record135 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records135 = static fn (): array => [
    $record135('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) VIRTUAL)', 1),
    $record135('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
    $record135('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    $record135('table', 'wp_option_audit', 'wp_option_audit', 5, 'CREATE TABLE wp_option_audit(audit_id INTEGER PRIMARY KEY, option_name TEXT, label TEXT)', 4),
    $record135('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, 'CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name_lc FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"', 5),
    $record135('view', 'wp_plain_options', 'wp_plain_options', 0, 'CREATE VIEW wp_plain_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = "yes"', 6),
];

$triggerDdl135 = [
    'CREATE TRIGGER wp_options_audit_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) SELECT option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_id = new.option_id; END',
    'CREATE TRIGGER wp_options_view_audit_au AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) SELECT option_name_lc, "view" FROM wp_autoloaded_options WHERE option_id = new.option_id; END',
    'CREATE TRIGGER wp_options_plain_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) SELECT option_name, "plain" FROM wp_plain_options WHERE option_id = new.option_id; END',
];

$plan135 = static fn (?array $ddl = null, ?array $records = null, ?array $prepared = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records135(),
    $ddl ?? $triggerDdl135,
    135,
    'main',
    $prepared ?? [
        ['id' => 'stale-trigger-audit-insert', 'schema_cookie' => 135, 'sql' => 'INSERT INTO wp_options(option_name) VALUES("siteurl")'],
        ['id' => 'stale-trigger-view-update', 'schema_cookie' => 136, 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
        ['id' => 'fresh-trigger-reader', 'schema_cookie' => 138, 'sql' => 'SELECT * FROM wp_option_audit'],
    ],
);

$op135 = static fn (int $index): array => $plan135()['operations'][$index];

$recordSql135 = static function (array $records, string $name): ?string {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record->sql;
        }
    }

    return null;
};

$tests = [
    'schema view trigger index reparse current source next135 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan135()['status']),
    'schema view trigger index reparse current source next135 before cookie' => static fn (TestRunner $t) => $t->same(135, $plan135()['before_schema_cookie']),
    'schema view trigger index reparse current source next135 after cookie advances per trigger' => static fn (TestRunner $t) => $t->same(138, $plan135()['after_schema_cookie']),
    'schema view trigger index reparse current source next135 changed flag' => static fn (TestRunner $t) => $t->same(true, $plan135()['schema_changed']),
    'schema view trigger index reparse current source next135 operation count' => static fn (TestRunner $t) => $t->same(3, count($plan135()['operations'])),
    'schema view trigger index reparse current source next135 table count stable' => static fn (TestRunner $t) => $t->same(2, $plan135()['table_count']),
    'schema view trigger index reparse current source next135 index count stable' => static fn (TestRunner $t) => $t->same(2, $plan135()['index_count']),
    'schema view trigger index reparse current source next135 invalidates two stale statements' => static fn (TestRunner $t) => $t->same(['stale-trigger-audit-insert', 'stale-trigger-view-update'], $plan135()['invalidated_prepared']),
    'schema view trigger index reparse current source next135 dependency list stable' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan135()['dependencies']),
    'schema view trigger index reparse current source next135 first trigger kind' => static fn (TestRunner $t) => $t->same('create_trigger', $op135(0)['kind']),
    'schema view trigger index reparse current source next135 second trigger kind' => static fn (TestRunner $t) => $t->same('create_trigger', $op135(1)['kind']),
    'schema view trigger index reparse current source next135 third trigger kind' => static fn (TestRunner $t) => $t->same('create_trigger', $op135(2)['kind']),
    'schema view trigger index reparse current source next135 first trigger name' => static fn (TestRunner $t) => $t->same('wp_options_audit_ai', $op135(0)['name']),
    'schema view trigger index reparse current source next135 second trigger name' => static fn (TestRunner $t) => $t->same('wp_options_view_audit_au', $op135(1)['name']),
    'schema view trigger index reparse current source next135 third trigger name' => static fn (TestRunner $t) => $t->same('wp_options_plain_ai', $op135(2)['name']),
    'schema view trigger index reparse current source next135 first trigger target' => static fn (TestRunner $t) => $t->same('wp_options', $op135(0)['table']),
    'schema view trigger index reparse current source next135 first trigger rootpage zero' => static fn (TestRunner $t) => $t->same(0, $op135(0)['rootpage']),
    'schema view trigger index reparse current source next135 first trigger rowid' => static fn (TestRunner $t) => $t->same(7, $op135(0)['rowid']),
    'schema view trigger index reparse current source next135 second trigger rowid' => static fn (TestRunner $t) => $t->same(8, $op135(1)['rowid']),
    'schema view trigger index reparse current source next135 third trigger rowid' => static fn (TestRunner $t) => $t->same(9, $op135(2)['rowid']),
    'schema view trigger index reparse current source next135 first body tables include audit and options' => static fn (TestRunner $t) => $t->same(['wp_option_audit', 'wp_options'], $op135(0)['body_source_tables']),
    'schema view trigger index reparse current source next135 first body no views' => static fn (TestRunner $t) => $t->same([], $op135(0)['body_source_views']),
    'schema view trigger index reparse current source next135 first indexed by captured' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op135(0)['body_indexed_by']),
    'schema view trigger index reparse current source next135 first generated columns captured' => static fn (TestRunner $t) => $t->same(['option_name_lc', 'option_value_len'], $op135(0)['generated_column_references']),
    'schema view trigger index reparse current source next135 first generated column count' => static fn (TestRunner $t) => $t->same(2, $op135(0)['generated_column_reference_count']),
    'schema view trigger index reparse current source next135 first generated index captured' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op135(0)['generated_index_references']),
    'schema view trigger index reparse current source next135 first generated index count' => static fn (TestRunner $t) => $t->same(1, $op135(0)['generated_index_reference_count']),
    'schema view trigger index reparse current source next135 first requires current source' => static fn (TestRunner $t) => $t->same(true, $op135(0)['current_source_reparse']),
    'schema view trigger index reparse current source next135 second body view captured' => static fn (TestRunner $t) => $t->same(['wp_autoloaded_options'], $op135(1)['body_source_views']),
    'schema view trigger index reparse current source next135 second view reference label' => static fn (TestRunner $t) => $t->same(['view:wp_autoloaded_options'], $op135(1)['view_references']),
    'schema view trigger index reparse current source next135 second body table captures audit only' => static fn (TestRunner $t) => $t->same(['wp_option_audit'], $op135(1)['body_source_tables']),
    'schema view trigger index reparse current source next135 second generated column ref from view select' => static fn (TestRunner $t) => $t->same(['option_name_lc'], $op135(1)['generated_column_references']),
    'schema view trigger index reparse current source next135 second inherits generated index refs from view' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op135(1)['generated_index_references']),
    'schema view trigger index reparse current source next135 second view forces current source' => static fn (TestRunner $t) => $t->same(true, $op135(1)['current_source_reparse']),
    'schema view trigger index reparse current source next135 third plain view captured' => static fn (TestRunner $t) => $t->same(['wp_plain_options'], $op135(2)['body_source_views']),
    'schema view trigger index reparse current source next135 third plain view reference label' => static fn (TestRunner $t) => $t->same(['view:wp_plain_options'], $op135(2)['view_references']),
    'schema view trigger index reparse current source next135 third plain view still reparses' => static fn (TestRunner $t) => $t->same(true, $op135(2)['current_source_reparse']),
    'schema view trigger index reparse current source next135 third no generated columns' => static fn (TestRunner $t) => $t->same([], $op135(2)['generated_column_references']),
    'schema view trigger index reparse current source next135 third no generated indexes' => static fn (TestRunner $t) => $t->same([], $op135(2)['generated_index_references']),
    'schema view trigger index reparse current source next135 trigger record stored' => static function (TestRunner $t) use ($plan135, $recordSql135): void {
        $t->same(true, str_contains((string) $recordSql135($plan135()['records'], 'wp_options_audit_ai'), 'INDEXED BY wp_options_generated_lookup'));
    },
    'schema view trigger index reparse current source next135 view trigger record stored' => static function (TestRunner $t) use ($plan135, $recordSql135): void {
        $t->same(true, str_contains((string) $recordSql135($plan135()['records'], 'wp_options_view_audit_au'), 'wp_autoloaded_options'));
    },
    'schema view trigger index reparse current source next135 plain trigger record stored' => static function (TestRunner $t) use ($plan135, $recordSql135): void {
        $t->same(true, str_contains((string) $recordSql135($plan135()['records'], 'wp_options_plain_ai'), 'wp_plain_options'));
    },
    'schema view trigger index reparse current source next135 catalog table list includes trigger targets' => static function (TestRunner $t) use ($plan135): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan135()['records']);
        $t->same(true, in_array('wp_options', array_column($catalog->execute('PRAGMA table_list')['rows'], 'name'), true));
    },
    'schema view trigger index reparse current source next135 schema record count' => static fn (TestRunner $t) => $t->same(9, count($plan135()['records'])),
    'schema view trigger index reparse current source next135 duplicate trigger no-op reason' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_audit_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 'CREATE TRIGGER wp_options_audit_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END']);
        $t->same('trigger_already_exists', $plan['operations'][1]['reason']);
    },
    'schema view trigger index reparse current source next135 duplicate trigger cookie advances once' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_audit_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 'CREATE TRIGGER wp_options_audit_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END']);
        $t->same(136, $plan['after_schema_cookie']);
    },
    'schema view trigger index reparse current source next135 quoted indexed by captured' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_quoted_ai AFTER INSERT ON "wp_options" BEGIN SELECT "option_name_lc" FROM "wp_options" INDEXED BY "wp_options_generated_lookup"; END']);
        $t->same(['wp_options_generated_lookup'], $plan['operations'][0]['body_indexed_by']);
    },
    'schema view trigger index reparse current source next135 quoted generated column captured' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_quoted_ai AFTER INSERT ON "wp_options" BEGIN SELECT "option_name_lc" FROM "wp_options" INDEXED BY "wp_options_generated_lookup"; END']);
        $t->same(['option_name_lc'], $plan['operations'][0]['generated_column_references']);
    },
    'schema view trigger index reparse current source next135 update body table captured' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_update_audit AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET label = new.option_name_lc WHERE option_name = old.option_name; END']);
        $t->same(['wp_option_audit'], $plan['operations'][0]['body_source_tables']);
    },
    'schema view trigger index reparse current source next135 update generated column captured' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_update_audit AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET label = new.option_name_lc WHERE option_name = old.option_name; END']);
        $t->same(['option_name_lc'], $plan['operations'][0]['generated_column_references']);
    },
    'schema view trigger index reparse current source next135 ordinary trigger not current source' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_plain_label AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) VALUES(new.option_name, "plain"); END']);
        $t->same(false, $plan['operations'][0]['current_source_reparse']);
    },
    'schema view trigger index reparse current source next135 ordinary trigger no generated refs' => static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135(['CREATE TRIGGER wp_options_plain_label AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) VALUES(new.option_name, "plain"); END']);
        $t->same([], $plan['operations'][0]['generated_column_references']);
    },
    'schema view trigger index reparse current source next135 rejects missing target table' => static function (TestRunner $t) use ($plan135): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan135(['CREATE TRIGGER broken AFTER INSERT ON missing_options BEGIN SELECT 1; END']));
    },
];

return $tests;
