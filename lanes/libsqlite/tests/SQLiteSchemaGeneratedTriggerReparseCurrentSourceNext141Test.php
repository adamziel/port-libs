<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record141 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records141 = static fn (): array => [
    $record141('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_hash TEXT AS (substr(option_value, 1, 8)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) STORED)', 1),
    $record141('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
    $record141('index', 'wp_options_hash_lookup', 'wp_options', 4, 'CREATE INDEX wp_options_hash_lookup ON wp_options(option_value_hash)', 3),
    $record141('table', 'wp_option_audit', 'wp_option_audit', 5, 'CREATE TABLE wp_option_audit(audit_id INTEGER PRIMARY KEY, option_id INTEGER, option_name TEXT, label TEXT)', 4),
    $record141('view', 'wp_autoloaded_generated_options', 'wp_autoloaded_generated_options', 0, 'CREATE VIEW wp_autoloaded_generated_options AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"', 5),
    $record141('view', 'wp_option_hashes', 'wp_option_hashes', 0, 'CREATE VIEW wp_option_hashes AS SELECT option_id, option_value_hash FROM wp_options INDEXED BY wp_options_hash_lookup', 6),
    $record141('view', 'wp_plain_options', 'wp_plain_options', 0, 'CREATE VIEW wp_plain_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = "yes"', 7),
];

$triggerDdl141 = [
    'CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_name_lc, "autoload" FROM wp_autoloaded_generated_options WHERE option_id = new.option_id; END',
    'CREATE TRIGGER wp_options_hash_view_au AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_value_hash, "hash" FROM wp_option_hashes WHERE option_id = new.option_id; END',
    'CREATE TRIGGER wp_options_generated_view_star_ad AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT *, "star" FROM wp_autoloaded_generated_options WHERE option_id = old.option_id; END',
    'CREATE TRIGGER wp_options_plain_view_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_name, "plain" FROM wp_plain_options WHERE option_id = new.option_id; END',
];

$plan141 = static fn (?array $ddl = null, ?array $records = null, ?array $prepared = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records141(),
    $ddl ?? $triggerDdl141,
    141,
    'main',
    $prepared ?? [
        ['id' => 'stale-options-insert', 'schema_cookie' => 141, 'sql' => 'INSERT INTO wp_options(option_name, option_value) VALUES (?, ?)'],
        ['id' => 'stale-options-update', 'schema_cookie' => 142, 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_id = ?'],
        ['id' => 'stale-options-delete', 'schema_cookie' => 143, 'sql' => 'DELETE FROM wp_options WHERE option_id = ?'],
        ['id' => 'fresh-audit-reader', 'schema_cookie' => 145, 'sql' => 'SELECT * FROM wp_option_audit'],
    ],
);

$op141 = static fn (int $index): array => $plan141()['operations'][$index];

$recordSql141 = static function (array $records, string $name): ?string {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record->sql;
        }
    }

    return null;
};

$value141 = static function (array $value, string $path): mixed {
    $cursor = $value;
    foreach (explode('.', $path) as $part) {
        if (preg_match('/^\[(.*)\]$/', $part, $match) === 1) {
            $part = $match[1];
        }
        $cursor = $cursor[$part];
    }

    return $cursor;
};

$cases141 = [
    'status ok' => [static fn (): mixed => $plan141()['status'], 'ok'],
    'before cookie' => [static fn (): mixed => $plan141()['before_schema_cookie'], 141],
    'after cookie advances for four triggers' => [static fn (): mixed => $plan141()['after_schema_cookie'], 145],
    'schema changed' => [static fn (): mixed => $plan141()['schema_changed'], true],
    'operation count' => [static fn (): mixed => count($plan141()['operations']), 4],
    'table count stable' => [static fn (): mixed => $plan141()['table_count'], 2],
    'index count stable' => [static fn (): mixed => $plan141()['index_count'], 2],
    'invalidates stale prepared statements' => [static fn (): mixed => $plan141()['invalidated_prepared'], ['stale-options-insert', 'stale-options-update', 'stale-options-delete']],
    'dependencies unchanged' => [static fn (): mixed => $plan141()['dependencies'], ['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog']],
    'first trigger kind' => [static fn (): mixed => $op141(0)['kind'], 'create_trigger'],
    'second trigger kind' => [static fn (): mixed => $op141(1)['kind'], 'create_trigger'],
    'third trigger kind' => [static fn (): mixed => $op141(2)['kind'], 'create_trigger'],
    'plain trigger kind' => [static fn (): mixed => $op141(3)['kind'], 'create_trigger'],
    'first trigger name' => [static fn (): mixed => $op141(0)['name'], 'wp_options_generated_view_ai'],
    'second trigger name' => [static fn (): mixed => $op141(1)['name'], 'wp_options_hash_view_au'],
    'third trigger name' => [static fn (): mixed => $op141(2)['name'], 'wp_options_generated_view_star_ad'],
    'plain trigger name' => [static fn (): mixed => $op141(3)['name'], 'wp_options_plain_view_ai'],
    'first trigger target' => [static fn (): mixed => $op141(0)['table'], 'wp_options'],
    'first trigger rowid' => [static fn (): mixed => $op141(0)['rowid'], 8],
    'second trigger rowid' => [static fn (): mixed => $op141(1)['rowid'], 9],
    'third trigger rowid' => [static fn (): mixed => $op141(2)['rowid'], 10],
    'plain trigger rowid' => [static fn (): mixed => $op141(3)['rowid'], 11],
    'first body tables insert audit only' => [static fn (): mixed => $op141(0)['body_source_tables'], ['wp_option_audit']],
    'first body view captured' => [static fn (): mixed => $op141(0)['body_source_views'], ['wp_autoloaded_generated_options']],
    'first view reference captured' => [static fn (): mixed => $op141(0)['view_references'], ['view:wp_autoloaded_generated_options']],
    'first generated columns come from view current source' => [static fn (): mixed => $op141(0)['generated_column_references'], ['option_name_lc', 'option_value_len']],
    'first generated column count' => [static fn (): mixed => $op141(0)['generated_column_reference_count'], 2],
    'first generated index refs stay direct only' => [static fn (): mixed => $op141(0)['generated_index_references'], []],
    'first generated index count stays zero' => [static fn (): mixed => $op141(0)['generated_index_reference_count'], 0],
    'first requires current source reparse' => [static fn (): mixed => $op141(0)['current_source_reparse'], true],
    'second body tables insert audit only' => [static fn (): mixed => $op141(1)['body_source_tables'], ['wp_option_audit']],
    'second body view captured' => [static fn (): mixed => $op141(1)['body_source_views'], ['wp_option_hashes']],
    'second view reference captured' => [static fn (): mixed => $op141(1)['view_references'], ['view:wp_option_hashes']],
    'second generated column comes from view current source' => [static fn (): mixed => $op141(1)['generated_column_references'], ['option_value_hash']],
    'second generated index refs stay direct only' => [static fn (): mixed => $op141(1)['generated_index_references'], []],
    'second requires current source reparse' => [static fn (): mixed => $op141(1)['current_source_reparse'], true],
    'third body view captured' => [static fn (): mixed => $op141(2)['body_source_views'], ['wp_autoloaded_generated_options']],
    'third generated columns come from star view' => [static fn (): mixed => $op141(2)['generated_column_references'], ['option_name_lc', 'option_value_len']],
    'third generated index refs stay direct only' => [static fn (): mixed => $op141(2)['generated_index_references'], []],
    'third requires current source reparse' => [static fn (): mixed => $op141(2)['current_source_reparse'], true],
    'plain trigger body view captured' => [static fn (): mixed => $op141(3)['body_source_views'], ['wp_plain_options']],
    'plain trigger view reference captured' => [static fn (): mixed => $op141(3)['view_references'], ['view:wp_plain_options']],
    'plain trigger has no generated column refs' => [static fn (): mixed => $op141(3)['generated_column_references'], []],
    'plain trigger has no generated index refs' => [static fn (): mixed => $op141(3)['generated_index_references'], []],
    'plain trigger still reparses because view source changes current source' => [static fn (): mixed => $op141(3)['current_source_reparse'], true],
    'schema record count includes triggers' => [static fn (): mixed => count($plan141()['records']), 11],
    'catalog sees first trigger' => [static fn (): mixed => $recordSql141($plan141()['records'], 'wp_options_generated_view_ai') !== null, true],
    'catalog stores generated view trigger SQL' => [static fn (): mixed => str_contains((string) $recordSql141($plan141()['records'], 'wp_options_generated_view_ai'), 'wp_autoloaded_generated_options'), true],
    'catalog stores hash view trigger SQL' => [static fn (): mixed => str_contains((string) $recordSql141($plan141()['records'], 'wp_options_hash_view_au'), 'wp_option_hashes'), true],
    'catalog stores star trigger SQL' => [static fn (): mixed => str_contains((string) $recordSql141($plan141()['records'], 'wp_options_generated_view_star_ad'), 'SELECT *, "star"'), true],
    'trigger-only ddl has no pragma samples' => [static fn (): mixed => $plan141()['pragma_samples'], []],
    'pragma table list includes audit table' => [static function () use ($plan141): mixed {
        $catalog = new SQLitePragmaSchemaCatalog($plan141()['records']);
        return in_array('wp_option_audit', array_column($catalog->execute('PRAGMA table_list')['rows'], 'name'), true);
    }, true],
    'duplicate trigger remains no op' => [static fn (): mixed => $plan141(['CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END', 'CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN SELECT 2; END'])['operations'][1]['reason'], 'trigger_already_exists'],
    'duplicate trigger cookie advances once' => [static fn (): mixed => $plan141(['CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END', 'CREATE TRIGGER wp_options_generated_view_ai AFTER INSERT ON wp_options BEGIN SELECT 2; END'])['after_schema_cookie'], 142],
    'quoted view source propagates generated columns' => [static fn (): mixed => $plan141(['CREATE TRIGGER wp_options_quoted_view AFTER INSERT ON "wp_options" BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_name_lc, "quoted" FROM "wp_autoloaded_generated_options" WHERE option_id = new.option_id; END'])['operations'][0]['generated_column_references'], ['option_name_lc', 'option_value_len']],
    'quoted view source keeps generated indexes direct only' => [static fn (): mixed => $plan141(['CREATE TRIGGER wp_options_quoted_view AFTER INSERT ON "wp_options" BEGIN INSERT INTO wp_option_audit(option_id, option_name, label) SELECT option_id, option_name_lc, "quoted" FROM "wp_autoloaded_generated_options" WHERE option_id = new.option_id; END'])['operations'][0]['generated_index_references'], []],
    'unresolved body source stays outside generated refs' => [static fn (): mixed => $plan141(['CREATE TRIGGER unresolved_body AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id) SELECT option_id FROM missing_view; END'])['operations'][0]['generated_column_references'], []],
    'unresolved body source still stores trigger' => [static fn (): mixed => $plan141(['CREATE TRIGGER unresolved_body AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id) SELECT option_id FROM missing_view; END'])['operations'][0]['changed'], true],
];

$tests = [];
foreach ($cases141 as $name => [$callback, $expected]) {
    $tests['schema generated trigger reparse current source next141 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['schema generated trigger reparse current source next141 path helper can read generated reference'] = static function (TestRunner $t) use ($plan141, $value141): void {
    $t->same('option_name_lc', $value141($plan141(), 'operations.0.generated_column_references.0'));
};

return $tests;
