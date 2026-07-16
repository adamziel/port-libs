<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerNameResolution;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
    $record('index', 'wp_options_autoload', 'wp_options', 3, "CREATE INDEX wp_options_autoload ON wp_options(autoload)", 2),
    $record('view', 'wp_option_names', 'wp_option_names', 0, 'CREATE VIEW wp_option_names AS SELECT option_id, option_name FROM wp_options', 3),
    $record('view', 'wp_option_names_star', 'wp_option_names_star', 0, 'CREATE VIEW wp_option_names_star AS SELECT * FROM wp_options WHERE autoload = "yes"', 4),
    $record('view', 'wp_option_generated_names', 'wp_option_generated_names', 0, 'CREATE VIEW wp_option_generated_names AS SELECT option_name_lc FROM wp_options', 5),
    $record('view', 'wp_other_settings', 'wp_other_settings', 0, 'CREATE VIEW wp_other_settings AS SELECT setting_name FROM wp_settings', 6),
    $record('table', 'wp_settings', 'wp_settings', 7, 'CREATE TABLE wp_settings(setting_id INTEGER PRIMARY KEY, setting_name TEXT)', 7),
    $record('trigger', 'wp_options_lc_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_lc_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(label) VALUES(new.option_name_lc); END', 8),
    $record('trigger', 'wp_options_bad_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_bad_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(label) VALUES(new.missing_generated); END', 9),
    $record('trigger', 'wp_other_settings_ai', 'wp_settings', 0, 'CREATE TRIGGER wp_other_settings_ai AFTER INSERT ON wp_settings BEGIN SELECT new.setting_name; END', 10),
];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
];

$plan = static fn (): array => SQLiteSchemaDdlReparsePlan::apply(
    $records(),
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    131,
    'main',
    [
        ['id' => 'select-option-names-current', 'schema_cookie' => 131, 'sql' => 'SELECT * FROM wp_option_names_star'],
        ['id' => 'select-option-names-reparsed', 'schema_cookie' => 132, 'sql' => 'SELECT option_name_lc FROM wp_option_generated_names'],
    ],
    ['wp_options' => $currentRows],
);

$byName = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing schema record {$name}");
};

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $part === 'count' ? count($value) : $value[$part];
    }

    return $value;
};

$tests = [
    'schema view trigger generated reparse current source next131 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan()['status']),
    'schema view trigger generated reparse current source next131 before cookie' => static fn (TestRunner $t) => $t->same(131, $plan()['before_schema_cookie']),
    'schema view trigger generated reparse current source next131 after cookie' => static fn (TestRunner $t) => $t->same(132, $plan()['after_schema_cookie']),
    'schema view trigger generated reparse current source next131 changed flag' => static fn (TestRunner $t) => $t->same(true, $plan()['schema_changed']),
    'schema view trigger generated reparse current source next131 operation kind' => static fn (TestRunner $t) => $t->same('alter_table_add_column', $plan()['operations'][0]['kind']),
    'schema view trigger generated reparse current source next131 operation table' => static fn (TestRunner $t) => $t->same('wp_options', $plan()['operations'][0]['table']),
    'schema view trigger generated reparse current source next131 operation column' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan()['operations'][0]['column']),
    'schema view trigger generated reparse current source next131 operation generated' => static fn (TestRunner $t) => $t->same(true, $plan()['operations'][0]['generated']),
    'schema view trigger generated reparse current source next131 checked current rows' => static fn (TestRunner $t) => $t->same(2, $plan()['operations'][0]['checked_rows']),
    'schema view trigger generated reparse current source next131 column count' => static fn (TestRunner $t) => $t->same(5, $plan()['operations'][0]['column_count']),
    'schema view trigger generated reparse current source next131 dependent count' => static fn (TestRunner $t) => $t->same(6, $plan()['operations'][0]['dependent_reparse_count']),
    'schema view trigger generated reparse current source next131 dependent index' => static fn (TestRunner $t) => $t->same('index:wp_options_autoload', $plan()['operations'][0]['dependent_reparse_records'][0]),
    'schema view trigger generated reparse current source next131 dependent plain view' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_option_names', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 dependent star view' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_option_names_star', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 dependent generated view' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_option_generated_names', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 dependent resolved trigger' => static fn (TestRunner $t) => $t->same(true, in_array('trigger:wp_options_lc_ai', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 dependent unresolved trigger' => static fn (TestRunner $t) => $t->same(true, in_array('trigger:wp_options_bad_ai', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 skips unrelated view' => static fn (TestRunner $t) => $t->same(false, in_array('view:wp_other_settings', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 skips unrelated trigger' => static fn (TestRunner $t) => $t->same(false, in_array('trigger:wp_other_settings_ai', $plan()['operations'][0]['dependent_reparse_records'], true)),
    'schema view trigger generated reparse current source next131 reports star expansion view' => static fn (TestRunner $t) => $t->same(['view:wp_option_names_star'], $plan()['operations'][0]['star_expansion_records']),
    'schema view trigger generated reparse current source next131 reports generated view' => static fn (TestRunner $t) => $t->same(['view:wp_option_generated_names'], $plan()['operations'][0]['generated_column_view_records']),
    'schema view trigger generated reparse current source next131 reports resolved trigger' => static fn (TestRunner $t) => $t->same(['trigger:wp_options_lc_ai'], $plan()['operations'][0]['resolved_trigger_records']),
    'schema view trigger generated reparse current source next131 reports unresolved trigger' => static fn (TestRunner $t) => $t->same(['trigger:wp_options_bad_ai'], $plan()['operations'][0]['unresolved_trigger_records']),
    'schema view trigger generated reparse current source next131 reports missing new reference' => static fn (TestRunner $t) => $t->same(['missing_generated'], $plan()['operations'][0]['trigger_missing_references']['wp_options_bad_ai']['new']),
    'schema view trigger generated reparse current source next131 reports no missing old references' => static fn (TestRunner $t) => $t->same([], $plan()['operations'][0]['trigger_missing_references']['wp_options_bad_ai']['old']),
    'schema view trigger generated reparse current source next131 invalidates current prepared only' => static fn (TestRunner $t) => $t->same(['select-option-names-current'], $plan()['invalidated_prepared']),
    'schema view trigger generated reparse current source next131 table count stable' => static fn (TestRunner $t) => $t->same(2, $plan()['table_count']),
    'schema view trigger generated reparse current source next131 index count stable' => static fn (TestRunner $t) => $t->same(1, $plan()['index_count']),
    'schema view trigger generated reparse current source next131 table sql includes generated column' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(true, str_contains((string) $byName($plan()['records'], 'wp_options')->sql, 'option_name_lc TEXT AS (lower(option_name)) VIRTUAL'));
    },
    'schema view trigger generated reparse current source next131 table rowid preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(1, $byName($plan()['records'], 'wp_options')->rowId);
    },
    'schema view trigger generated reparse current source next131 table root preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(2, $byName($plan()['records'], 'wp_options')->rootPage);
    },
    'schema view trigger generated reparse current source next131 plain view sql preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same('CREATE VIEW wp_option_names AS SELECT option_id, option_name FROM wp_options', $byName($plan()['records'], 'wp_option_names')->sql);
    },
    'schema view trigger generated reparse current source next131 star view sql preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same('CREATE VIEW wp_option_names_star AS SELECT * FROM wp_options WHERE autoload = "yes"', $byName($plan()['records'], 'wp_option_names_star')->sql);
    },
    'schema view trigger generated reparse current source next131 generated view sql preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same('CREATE VIEW wp_option_generated_names AS SELECT option_name_lc FROM wp_options', $byName($plan()['records'], 'wp_option_generated_names')->sql);
    },
    'schema view trigger generated reparse current source next131 trigger sql preserved' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(true, str_contains((string) $byName($plan()['records'], 'wp_options_lc_ai')->sql, 'new.option_name_lc'));
    },
    'schema view trigger generated reparse current source next131 table xinfo sample exists' => static fn (TestRunner $t) => $t->same('table_xinfo', $plan()['pragma_samples']['table_xinfo:wp_options']['pragma']),
    'schema view trigger generated reparse current source next131 table xinfo includes generated' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name']),
    'schema view trigger generated reparse current source next131 table xinfo generated hidden' => static fn (TestRunner $t) => $t->same(2, $plan()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['hidden']),
    'schema view trigger generated reparse current source next131 table info omits generated' => static function (TestRunner $t) use ($plan): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan()['records']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload'], array_column($catalog->execute('PRAGMA table_info(wp_options)')['rows'], 'name'));
    },
    'schema view trigger generated reparse current source next131 table xinfo column list' => static function (TestRunner $t) use ($plan): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan()['records']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload', 'option_name_lc'], array_column($catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'], 'name'));
    },
    'schema view trigger generated reparse current source next131 trigger resolver sees generated column' => static function (TestRunner $t) use ($plan): void {
        $t->same('resolved', SQLiteViewTriggerNameResolution::resolveTrigger($plan()['records'], 'wp_options_lc_ai')['status']);
    },
    'schema view trigger generated reparse current source next131 trigger resolver keeps missing column unresolved' => static function (TestRunner $t) use ($plan): void {
        $t->same('unresolved', SQLiteViewTriggerNameResolution::resolveTrigger($plan()['records'], 'wp_options_bad_ai')['status']);
    },
    'schema view trigger generated reparse current source next131 trigger resolver columns include generated' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array('option_name_lc', SQLiteViewTriggerNameResolution::resolveTrigger($plan()['records'], 'wp_options_lc_ai')['columns'], true));
    },
    'schema view trigger generated reparse current source next131 trigger summary unresolved count' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, SQLiteViewTriggerNameResolution::summary($plan()['records'])['unresolved']);
    },
    'schema view trigger generated reparse current source next131 dependencies stable' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan()['dependencies']),
    'schema view trigger generated reparse current source next131 value path helper count' => static fn (TestRunner $t) => $t->same(6, $valueAt($plan(), 'operations.0.dependent_reparse_records.count')),
];

return $tests;
