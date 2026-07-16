<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer, key_name text, key_value text, load_policy text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_stage_view', 'app_setting_stage_view', 0, 'CREATE VIEW app_setting_stage_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_stage_io_insert', 'app_setting_stage_view', 0, 'CREATE TRIGGER app_setting_stage_io_insert INSTEAD OF INSERT ON app_setting_stage_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING key_name, key_value; END', 3),
];

$plan = SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
    $schema,
    'app_setting_stage_io_insert',
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'touched' => 'seed'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'touched' => 'seed'],
    ],
    [
        ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ],
    [
        ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://new.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'module_registry', 'value' => 'enabled', 'load_policy_flag' => 'no'],
        ['import_id' => 103, 'name' => 'landing_url', 'value' => 'https://skipped.test', 'load_policy_flag' => 'skip'],
    ],
    [
        'import_id' => 'setting_id',
        'name' => 'key_name',
        'value' => 'key_value',
        'load_policy_flag' => 'load_policy',
    ],
    ['key_name'],
    [
        'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
        'touched' => static fn (array $old, array $incoming): string => 'upsert',
    ],
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true],
    [
        [
            'name' => 'app_settings_bu_rekey_meta',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'match' => 'old.setting_id',
            'set_child_key' => 'new.setting_id',
            'values' => ['name' => 'new.key_name', 'old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
        ],
        [
            'name' => 'app_settings_au_touch',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'set-new',
            'set' => ['touched' => 'after-update'],
            'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
        ],
        [
            'name' => 'app_settings_ai_meta',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'insert-child',
            'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'view-import', 'meta_value' => 'new.key_name'],
            'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
        ],
    ],
    static fn (array $old, array $incoming): bool => $incoming['load_policy'] !== 'skip',
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'excluded.key_value', 'as' => 'incomingValue'],
    ],
    'app_current_next',
);

echo json_encode([
    'scenario' => 'application-trigger-returning-upsert-view-current-next52',
    'applicationUse' => 'Preview copied app_settings imports routed through an INSTEAD OF view trigger whose UPSERT RETURNING rows expose current and next table images without requiring ext/sqlite.',
    'changes' => $plan['changes'],
    'rolledBack' => $plan['rolled_back'],
    'yieldStream' => array_map(static fn (array $row): array => [
        'ordinal' => $row['ordinal'],
        'event' => $row['event'],
        'status' => $row['status'],
        'name' => $row['incoming_row']['key_name'],
        'currentValue' => $row['current_row']['key_value'] ?? null,
        'nextValue' => $row['next_row']['key_value'] ?? null,
        'returning' => $row['returning'],
    ], $plan['yield_stream']),
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
