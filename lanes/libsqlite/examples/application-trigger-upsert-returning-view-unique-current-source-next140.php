<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer UNIQUE, key_name text UNIQUE, key_value text, load_policy text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_import_view', 'app_setting_import_view', 0, 'CREATE VIEW app_setting_import_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_import_io_insert', 'app_setting_import_view', 0, 'CREATE TRIGGER app_setting_import_io_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING setting_id, key_name, key_value; END', 3),
];

$plan = SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
    $schema,
    'app_setting_import_io_insert',
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'touched' => 'seed'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'touched' => 'seed'],
        ['setting_id' => 3, 'key_name' => 'display_title', 'key_value' => 'Old Title', 'load_policy' => 'no', 'touched' => 'seed'],
    ],
    [
        ['meta_id' => 11, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 12, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 13, 'setting_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
    ],
    [
        ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://first.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 2, 'name' => 'display_title', 'value' => 'collides-with-landing_url-id', 'load_policy_flag' => 'yes'],
        ['import_id' => 105, 'name' => 'unseen_setting', 'value' => 'nope', 'load_policy_flag' => 'no'],
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
            'values' => ['name' => 'new.key_name', 'new_key' => 'new.setting_id'],
        ],
    ],
    null,
    [
        ['expr' => 'new.setting_id', 'as' => 'id'],
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'excluded.setting_id', 'as' => 'incomingId'],
    ],
    'next140',
    [['key_name'], ['setting_id']],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-view-unique-current-source-next140',
    'applicationUse' => 'Preview a copied app_settings import routed through an INSTEAD OF view trigger, where secondary UNIQUE setting_id conflicts roll back previously yielded UPSERT RETURNING rows.',
    'rolledBack' => $plan['rolled_back'],
    'rolledBackAtOrdinal' => $plan['rolled_back_at_ordinal'],
    'rollbackReasonContainsUnique' => str_contains((string) $plan['rollback_reason'], 'unique constraint conflict'),
    'changes' => $plan['changes'],
    'parentIdsAfterRollback' => array_column($plan['parent'], 'setting_id'),
    'yieldOrdinalsBeforeRollback' => array_column($plan['yield_stream'], 'ordinal'),
    'returningRows' => $plan['returning_rows'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['rolledBack'] === true);
    assert($summary['rolledBackAtOrdinal'] === 1);
    assert($summary['rollbackReasonContainsUnique'] === true);
    assert($summary['changes'] === 0);
    assert($summary['parentIdsAfterRollback'] === [1, 2, 3]);
    assert($summary['yieldOrdinalsBeforeRollback'] === [0]);
    assert($summary['returningRows'] === []);
    echo "application-trigger-upsert-returning-view-unique-current-source-next140 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
