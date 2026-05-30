<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_stage_view', 'wp_option_stage_view', 0, 'CREATE VIEW wp_option_stage_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_stage_io_insert', 'wp_option_stage_view', 0, 'CREATE TRIGGER wp_option_stage_io_insert INSTEAD OF INSERT ON wp_option_stage_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_name, option_value; END', 3),
];

$plan = SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
    $schema,
    'wp_option_stage_io_insert',
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'touched' => 'seed'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'touched' => 'seed'],
    ],
    [
        ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ],
    [
        ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://new.test', 'autoload_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'migration_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
        ['import_id' => 103, 'name' => 'home', 'value' => 'https://skipped.test', 'autoload_flag' => 'skip'],
    ],
    [
        'import_id' => 'option_id',
        'name' => 'option_name',
        'value' => 'option_value',
        'autoload_flag' => 'autoload',
    ],
    ['option_name'],
    [
        'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
        'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
        'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
        'touched' => static fn (array $old, array $incoming): string => 'upsert',
    ],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    [
        [
            'name' => 'wp_options_bu_rekey_meta',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'match' => 'old.option_id',
            'set_child_key' => 'new.option_id',
            'values' => ['name' => 'new.option_name', 'old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
        ],
        [
            'name' => 'wp_options_au_touch',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'set-new',
            'set' => ['touched' => 'after-update'],
            'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
        ],
        [
            'name' => 'wp_options_ai_meta',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'insert-child',
            'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'view-import', 'meta_value' => 'new.option_name'],
            'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
        ],
    ],
    static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip',
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'excluded.option_value', 'as' => 'incomingValue'],
    ],
    'wp_current_next',
);

echo json_encode([
    'scenario' => 'application-trigger-returning-upsert-view-current-next52',
    'applicationUse' => 'Preview copied wp_options imports routed through an INSTEAD OF view trigger whose UPSERT RETURNING rows expose current and next table images without requiring ext/sqlite.',
    'changes' => $plan['changes'],
    'rolledBack' => $plan['rolled_back'],
    'yieldStream' => array_map(static fn (array $row): array => [
        'ordinal' => $row['ordinal'],
        'event' => $row['event'],
        'status' => $row['status'],
        'name' => $row['incoming_row']['option_name'],
        'currentValue' => $row['current_row']['option_value'] ?? null,
        'nextValue' => $row['next_row']['option_value'] ?? null,
        'returning' => $row['returning'],
    ], $plan['yield_stream']),
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
