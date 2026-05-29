<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record133 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records133 = static fn (): array => [
    $record133('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT NOT NULL, autoload TEXT DEFAULT "yes", option_slug TEXT AS (lower(option_name)) STORED)', 1),
    $record133('trigger', 'wp_options_generated_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_generated_au AFTER UPDATE OF option_value_len ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, old_slug, value_len) VALUES(new.option_id, old.option_slug, new.option_value_len); END', 2),
    $record133('trigger', 'wp_options_slug_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_slug_au AFTER UPDATE OF option_slug ON wp_options BEGIN SELECT new.option_slug, old.option_slug; END', 3),
    $record133('trigger', 'wp_options_plain_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_plain_ai AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 4),
    $record133('trigger', 'wp_posts_ai', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ai AFTER INSERT ON wp_posts BEGIN SELECT new.post_title; END', 5),
];

$rows133 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => 'a:1:{}', 'autoload' => 'no'],
];

$prepared133 = [
    ['id' => 'trigger-generated-update', 'schema_cookie' => 133, 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
    ['id' => 'fresh-generated-reader', 'schema_cookie' => 134, 'sql' => 'SELECT option_value_len FROM wp_options'],
];

$plan133 = static fn (?array $ddl = null, ?array $records = null): array => SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan::plan(
    $records ?? $records133(),
    $ddl ?? ['ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 0)'],
    133,
    'main',
    $prepared133,
    ['wp_options' => $rows133],
);

$tests = [
    'schema alter trigger generated current source next133 operation' => static fn (TestRunner $t) => $t->same('schema-alter-trigger-generated-current-source-next133', $plan133()['operation']),
    'schema alter trigger generated current source next133 status' => static fn (TestRunner $t) => $t->same('trigger-reparse-required', $plan133()['status']),
    'schema alter trigger generated current source next133 schema' => static fn (TestRunner $t) => $t->same('main', $plan133()['schema']),
    'schema alter trigger generated current source next133 table before' => static fn (TestRunner $t) => $t->same('wp_options', $plan133()['table_before']),
    'schema alter trigger generated current source next133 table after' => static fn (TestRunner $t) => $t->same('wp_options', $plan133()['table_after']),
    'schema alter trigger generated current source next133 cookie before' => static fn (TestRunner $t) => $t->same(133, $plan133()['schema_cookie_before']),
    'schema alter trigger generated current source next133 cookie after' => static fn (TestRunner $t) => $t->same(134, $plan133()['schema_cookie_after']),
    'schema alter trigger generated current source next133 cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan133()['schema_cookie_changed']),
    'schema alter trigger generated current source next133 ddl operations' => static fn (TestRunner $t) => $t->same(['alter_table_add_column'], $plan133()['ddl_operations']),
    'schema alter trigger generated current source next133 generated before' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan133()['generated_before']),
    'schema alter trigger generated current source next133 generated after' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $plan133()['generated_after']),
    'schema alter trigger generated current source next133 generated added' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan133()['generated_added']),
    'schema alter trigger generated current source next133 reparse trigger list' => static fn (TestRunner $t) => $t->same(['wp_options_generated_au'], $plan133()['reparse_triggers']),
    'schema alter trigger generated current source next133 transition count' => static fn (TestRunner $t) => $t->same(1, count($plan133()['trigger_transitions'])),
    'schema alter trigger generated current source next133 transition name' => static fn (TestRunner $t) => $t->same('wp_options_generated_au', $plan133()['trigger_transitions'][0]['name']),
    'schema alter trigger generated current source next133 transition event' => static fn (TestRunner $t) => $t->same('update', $plan133()['trigger_transitions'][0]['event']),
    'schema alter trigger generated current source next133 transition table before' => static fn (TestRunner $t) => $t->same('wp_options', $plan133()['trigger_transitions'][0]['table_before']),
    'schema alter trigger generated current source next133 transition table after' => static fn (TestRunner $t) => $t->same('wp_options', $plan133()['trigger_transitions'][0]['table_after']),
    'schema alter trigger generated current source next133 trigger cookie before' => static fn (TestRunner $t) => $t->same(133, $plan133()['trigger_transitions'][0]['schema_cookie_before']),
    'schema alter trigger generated current source next133 trigger cookie after' => static fn (TestRunner $t) => $t->same(134, $plan133()['trigger_transitions'][0]['schema_cookie_after']),
    'schema alter trigger generated current source next133 current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $plan133()['trigger_transitions'][0]['current_status']),
    'schema alter trigger generated current source next133 next resolved' => static fn (TestRunner $t) => $t->same('resolved', $plan133()['trigger_transitions'][0]['next_status']),
    'schema alter trigger generated current source next133 update of before' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan133()['trigger_transitions'][0]['update_of_before']),
    'schema alter trigger generated current source next133 update of after' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan133()['trigger_transitions'][0]['update_of_after']),
    'schema alter trigger generated current source next133 generated before trigger' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan133()['trigger_transitions'][0]['generated_before']),
    'schema alter trigger generated current source next133 generated after trigger' => static fn (TestRunner $t) => $t->same(['option_value_len', 'option_slug'], $plan133()['trigger_transitions'][0]['generated_after']),
    'schema alter trigger generated current source next133 generated added to trigger' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan133()['trigger_transitions'][0]['generated_added_to_trigger']),
    'schema alter trigger generated current source next133 resolved missing generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan133()['trigger_transitions'][0]['resolved_missing_generated']),
    'schema alter trigger generated current source next133 new refs after' => static fn (TestRunner $t) => $t->same(['option_id', 'option_value_len'], $plan133()['trigger_transitions'][0]['new_references_after']),
    'schema alter trigger generated current source next133 old refs after' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan133()['trigger_transitions'][0]['old_references_after']),
    'schema alter trigger generated current source next133 reprepare reason' => static fn (TestRunner $t) => $t->same('schema-cookie-generated-trigger-current-source', $plan133()['trigger_transitions'][0]['reprepare_reason']),
    'schema alter trigger generated current source next133 invalidates stale prepared' => static fn (TestRunner $t) => $t->same(['trigger-generated-update'], $plan133()['invalidated_prepared']),
    'schema alter trigger generated current source next133 current source required' => static fn (TestRunner $t) => $t->same(true, $plan133()['current_source_required']),
    'schema alter trigger generated current source next133 table xinfo after count' => static fn (TestRunner $t) => $t->same(6, count($plan133()['table_xinfo_after'])),
    'schema alter trigger generated current source next133 table xinfo generated name' => static fn (TestRunner $t) => $t->same('option_value_len', $plan133()['table_xinfo_after'][5]['name']),
    'schema alter trigger generated current source next133 table xinfo generated hidden' => static fn (TestRunner $t) => $t->same(2, $plan133()['table_xinfo_after'][5]['hidden']),
    'schema alter trigger generated current source next133 dependency closure' => static fn (TestRunner $t) => $t->same(true, str_contains($plan133()['dependency_closure'], 'no new support component needed')),
    'schema alter trigger generated current source next133 non overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan133()['non_overlap'], 'next117')),
    'schema alter trigger generated current source next133 base dependency' => static fn (TestRunner $t) => $t->same(true, in_array('schema-sql-reparse', $plan133()['dependencies'], true)),
    'schema alter trigger generated current source next133 trigger dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-generated-current-source-next133', $plan133()['dependencies'], true)),
    'schema alter trigger generated current source next133 alter dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-alter-table-generated-column-current-source', $plan133()['dependencies'], true)),
];

$tests['schema alter trigger generated current source next133 rename transition table changes'] = static function (TestRunner $t) use ($plan133): void {
    $plan = $plan133([
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 0)',
        'ALTER TABLE wp_options RENAME TO wp_site_options',
    ]);
    $t->same('wp_site_options', $plan['table_after']);
    $t->same(['alter_table_add_column', 'alter_table_rename'], $plan['ddl_operations']);
    $t->same(135, $plan['schema_cookie_after']);
    $t->same('wp_site_options', $plan['trigger_transitions'][0]['table_after']);
};

$tests['schema alter trigger generated current source next133 stable ordinary trigger excluded'] = static function (TestRunner $t) use ($plan133): void {
    $names = $plan133()['reparse_triggers'];
    $t->same(false, in_array('wp_options_plain_ai', $names, true));
    $t->same(false, in_array('wp_options_slug_au', $names, true));
    $t->same(false, in_array('wp_posts_ai', $names, true));
};

$tests['schema alter trigger generated current source next133 no trigger transition for ordinary column'] = static function (TestRunner $t) use ($plan133): void {
    $plan = $plan133(['ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core"']);
    $t->same('stable', $plan['status']);
    $t->same([], $plan['trigger_transitions']);
    $t->same(['option_slug'], $plan['generated_after']);
};

$tests['schema alter trigger generated current source next133 quoted update of generated column'] = static function (TestRunner $t) use ($record133, $plan133): void {
    $records = [
        $record133('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
        $record133('trigger', 'wp_options_quote_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_quote_au AFTER UPDATE OF "option value len" ON wp_options BEGIN SELECT new."option value len"; END', 2),
    ];
    $plan = $plan133(['ALTER TABLE wp_options ADD COLUMN "option value len" INTEGER AS (length(option_value)) VIRTUAL'], $records);
    $t->same(['wp_options_quote_au'], $plan['reparse_triggers']);
    $t->same(['option value len'], $plan['generated_added']);
    $t->same(['option value len'], $plan['trigger_transitions'][0]['generated_added_to_trigger']);
};

$tests['schema alter trigger generated current source next133 rejects missing table'] = static function (TestRunner $t) use ($record133): void {
    $records = [$record133('trigger', 'orphan', 'wp_options', 0, 'CREATE TRIGGER orphan AFTER INSERT ON wp_options BEGIN SELECT new.option_id; END', 1)];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan::plan($records, ['ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL']));
};

return $tests;
