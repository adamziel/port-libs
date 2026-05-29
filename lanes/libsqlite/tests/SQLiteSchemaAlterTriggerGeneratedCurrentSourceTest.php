<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$recordsFactory = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT NOT NULL, autoload TEXT DEFAULT "yes", option_slug TEXT AS (lower(option_name)) STORED)', 1),
    $record('trigger', 'wp_options_generated_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_generated_au AFTER UPDATE OF option_value_len ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, old_slug, value_len) VALUES(new.option_id, old.option_slug, new.option_value_len); END', 2),
    $record('trigger', 'wp_options_slug_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_slug_au AFTER UPDATE OF option_slug ON wp_options BEGIN SELECT new.option_slug, old.option_slug; END', 3),
    $record('trigger', 'wp_options_plain_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_plain_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 4),
    $record('trigger', 'wp_posts_ai', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ai AFTER INSERT ON wp_posts BEGIN SELECT new.post_title; END', 5),
];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => 'a:1:{}', 'autoload' => 'no'],
];

$prepared = [
    ['id' => 'trigger-generated-update', 'schema_cookie' => 133, 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
    ['id' => 'fresh-generated-reader', 'schema_cookie' => 134, 'sql' => 'SELECT option_value_len FROM wp_options'],
];

$planFactory = static fn (?array $ddl = null, ?array $records = null): array => SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan::plan(
    $records ?? $recordsFactory(),
    $ddl ?? ['ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 0)'],
    133,
    'main',
    $prepared,
    ['wp_options' => $rows],
);

$tests = [
    'schema alter trigger generated current source operation' => static fn (TestRunner $t) => $t->same('schema-alter-trigger-generated-current-source', $planFactory()['operation']),
    'schema alter trigger generated current source status' => static fn (TestRunner $t) => $t->same('trigger-reparse-required', $planFactory()['status']),
    'schema alter trigger generated current source schema' => static fn (TestRunner $t) => $t->same('main', $planFactory()['schema']),
    'schema alter trigger generated current source table before' => static fn (TestRunner $t) => $t->same('wp_options', $planFactory()['table_before']),
    'schema alter trigger generated current source table after' => static fn (TestRunner $t) => $t->same('wp_options', $planFactory()['table_after']),
    'schema alter trigger generated current source cookie before' => static fn (TestRunner $t) => $t->same(133, $planFactory()['schema_cookie_before']),
    'schema alter trigger generated current source cookie after' => static fn (TestRunner $t) => $t->same(134, $planFactory()['schema_cookie_after']),
    'schema alter trigger generated current source cookie changed' => static fn (TestRunner $t) => $t->same(true, $planFactory()['schema_cookie_changed']),
    'schema alter trigger generated current source ddl operations' => static fn (TestRunner $t) => $t->same(['alter_table_add_column'], $planFactory()['ddl_operations']),
    'schema alter trigger generated current source generated before' => static fn (TestRunner $t) => $t->same(['option_slug'], $planFactory()['generated_before']),
    'schema alter trigger generated current source generated after' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $planFactory()['generated_after']),
    'schema alter trigger generated current source generated added' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['generated_added']),
    'schema alter trigger generated current source reparse trigger list' => static fn (TestRunner $t) => $t->same(['wp_options_generated_au'], $planFactory()['reparse_triggers']),
    'schema alter trigger generated current source transition count' => static fn (TestRunner $t) => $t->same(1, count($planFactory()['trigger_transitions'])),
    'schema alter trigger generated current source transition name' => static fn (TestRunner $t) => $t->same('wp_options_generated_au', $planFactory()['trigger_transitions'][0]['name']),
    'schema alter trigger generated current source transition event' => static fn (TestRunner $t) => $t->same('update', $planFactory()['trigger_transitions'][0]['event']),
    'schema alter trigger generated current source transition table before' => static fn (TestRunner $t) => $t->same('wp_options', $planFactory()['trigger_transitions'][0]['table_before']),
    'schema alter trigger generated current source transition table after' => static fn (TestRunner $t) => $t->same('wp_options', $planFactory()['trigger_transitions'][0]['table_after']),
    'schema alter trigger generated current source trigger cookie before' => static fn (TestRunner $t) => $t->same(133, $planFactory()['trigger_transitions'][0]['schema_cookie_before']),
    'schema alter trigger generated current source trigger cookie after' => static fn (TestRunner $t) => $t->same(134, $planFactory()['trigger_transitions'][0]['schema_cookie_after']),
    'schema alter trigger generated current source current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $planFactory()['trigger_transitions'][0]['current_status']),
    'schema alter trigger generated current source next resolved' => static fn (TestRunner $t) => $t->same('resolved', $planFactory()['trigger_transitions'][0]['next_status']),
    'schema alter trigger generated current source update of before' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['trigger_transitions'][0]['update_of_before']),
    'schema alter trigger generated current source update of after' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['trigger_transitions'][0]['update_of_after']),
    'schema alter trigger generated current source generated before trigger' => static fn (TestRunner $t) => $t->same(['option_slug'], $planFactory()['trigger_transitions'][0]['generated_before']),
    'schema alter trigger generated current source generated after trigger' => static fn (TestRunner $t) => $t->same(['option_value_len', 'option_slug'], $planFactory()['trigger_transitions'][0]['generated_after']),
    'schema alter trigger generated current source generated added to trigger' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['trigger_transitions'][0]['generated_added_to_trigger']),
    'schema alter trigger generated current source resolved missing generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['trigger_transitions'][0]['resolved_missing_generated']),
    'schema alter trigger generated current source new refs after' => static fn (TestRunner $t) => $t->same(['option_id', 'option_value_len'], $planFactory()['trigger_transitions'][0]['new_references_after']),
    'schema alter trigger generated current source old refs after' => static fn (TestRunner $t) => $t->same(['option_slug'], $planFactory()['trigger_transitions'][0]['old_references_after']),
    'schema alter trigger generated current source reprepare reason' => static fn (TestRunner $t) => $t->same('schema-cookie-generated-trigger-current-source', $planFactory()['trigger_transitions'][0]['reprepare_reason']),
    'schema alter trigger generated current source invalidates stale prepared' => static fn (TestRunner $t) => $t->same(['trigger-generated-update'], $planFactory()['invalidated_prepared']),
    'schema alter trigger generated current source current source required' => static fn (TestRunner $t) => $t->same(true, $planFactory()['current_source_required']),
    'schema alter trigger generated current source table xinfo after count' => static fn (TestRunner $t) => $t->same(6, count($planFactory()['table_xinfo_after'])),
    'schema alter trigger generated current source table xinfo generated name' => static fn (TestRunner $t) => $t->same('option_value_len', $planFactory()['table_xinfo_after'][5]['name']),
    'schema alter trigger generated current source table xinfo generated hidden' => static fn (TestRunner $t) => $t->same(2, $planFactory()['table_xinfo_after'][5]['hidden']),
    'schema alter trigger generated current source dependency closure' => static fn (TestRunner $t) => $t->same(true, str_contains($planFactory()['dependency_closure'], 'no new support component needed')),
    'schema alter trigger generated current source non overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($planFactory()['non_overlap'], 'standalone ALTER generated view/trigger helper')),
    'schema alter trigger generated current source base dependency' => static fn (TestRunner $t) => $t->same(true, in_array('schema-sql-reparse', $planFactory()['dependencies'], true)),
    'schema alter trigger generated current source trigger dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-generated-current-source', $planFactory()['dependencies'], true)),
    'schema alter trigger generated current source alter dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-alter-table-generated-column-current-source', $planFactory()['dependencies'], true)),
];

$tests['schema alter trigger generated current source rename transition table changes'] = static function (TestRunner $t) use ($planFactory): void {
    $plan = $planFactory([
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 0)',
        'ALTER TABLE wp_options RENAME TO wp_site_options',
    ]);
    $t->same('wp_site_options', $plan['table_after']);
    $t->same(['alter_table_add_column', 'alter_table_rename'], $plan['ddl_operations']);
    $t->same(135, $plan['schema_cookie_after']);
    $t->same('wp_site_options', $plan['trigger_transitions'][0]['table_after']);
};

$tests['schema alter trigger generated current source stable ordinary trigger excluded'] = static function (TestRunner $t) use ($planFactory): void {
    $names = $planFactory()['reparse_triggers'];
    $t->same(false, in_array('wp_options_plain_ai', $names, true));
    $t->same(false, in_array('wp_options_slug_au', $names, true));
    $t->same(false, in_array('wp_posts_ai', $names, true));
};

$tests['schema alter trigger generated current source no trigger transition for ordinary column'] = static function (TestRunner $t) use ($planFactory): void {
    $plan = $planFactory(['ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core"']);
    $t->same('stable', $plan['status']);
    $t->same([], $plan['trigger_transitions']);
    $t->same(['option_slug'], $plan['generated_after']);
};

$tests['schema alter trigger generated current source quoted update of generated column'] = static function (TestRunner $t) use ($record, $planFactory): void {
    $records = [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
        $record('trigger', 'wp_options_quote_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_quote_au AFTER UPDATE OF "option value len" ON wp_options BEGIN SELECT new."option value len"; END', 2),
    ];
    $plan = $planFactory(['ALTER TABLE wp_options ADD COLUMN "option value len" INTEGER AS (length(option_value)) VIRTUAL'], $records);
    $t->same(['wp_options_quote_au'], $plan['reparse_triggers']);
    $t->same(['option value len'], $plan['generated_added']);
    $t->same(['option value len'], $plan['trigger_transitions'][0]['generated_added_to_trigger']);
};

$tests['schema alter trigger generated current source rejects missing table'] = static function (TestRunner $t) use ($record): void {
    $records = [$record('trigger', 'orphan', 'wp_options', 0, 'CREATE TRIGGER orphan AFTER INSERT ON wp_options BEGIN SELECT new.option_id; END', 1)];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan::plan($records, ['ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL']));
};

return $tests;
