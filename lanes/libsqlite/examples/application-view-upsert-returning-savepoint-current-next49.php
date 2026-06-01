<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewUpsertReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer, key_name text, key_value text, load_policy text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_import_view', 'app_setting_import_view', 0, 'CREATE VIEW app_setting_import_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_import_view_io_insert', 'app_setting_import_view', 0, 'CREATE TRIGGER app_setting_import_view_io_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING key_name; END', 3),
];

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing.test', 'load_policy' => 'yes'],
];
$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];
$mapping = [
    'import_id' => 'setting_id',
    'name' => 'key_name',
    'value' => 'key_value',
    'load_policy_flag' => 'load_policy',
];
$assignments = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];
$foreignKey = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true];
$triggers = [[
    'name' => 'app_settings_bu_rekey_meta',
    'timing' => 'before',
    'event' => 'update',
    'action' => 'update-child',
    'match' => 'old.setting_id',
    'set_child_key' => 'new.setting_id',
    'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
]];
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
];

$committed = SQLiteViewUpsertReturningSavepointPlan::execute(
    $schema,
    'app_setting_import_view_io_insert',
    $parents,
    $children,
    [
        ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://new.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'fresh_module', 'value' => 'enabled', 'load_policy_flag' => 'no'],
    ],
    $mapping,
    ['key_name'],
    $assignments,
    $foreignKey,
    $triggers,
    null,
    $returning,
    'app_view_import',
);

$rolledBack = SQLiteViewUpsertReturningSavepointPlan::execute(
    $schema,
    'app_setting_import_view_io_insert',
    $parents,
    $children,
    [
        ['import_id' => 201, 'name' => 'base_url', 'value' => 'first', 'load_policy_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'landing_url', 'value' => 'bad', 'load_policy_flag' => 'yes'],
    ],
    $mapping,
    ['key_name'],
    $assignments,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => false],
    [[
        'name' => 'app_settings_bu_orphan_landing',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'when' => ['new.key_name', '=', 'landing_url'],
        'match' => 'old.setting_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.key_name'],
    ]],
    null,
    $returning,
    'app_view_import',
);

echo json_encode([
    'committedNames' => array_column($committed['parent'], 'key_name'),
    'committedReturning' => $committed['view_returning_rows'],
    'committedChanges' => $committed['changes'],
    'rollbackNames' => array_column($rolledBack['parent'], 'key_name'),
    'rollbackReturningCommitted' => $rolledBack['returning_rows'],
    'rollbackDiagnosticReturning' => $rolledBack['view_returning_rows'],
    'rollbackReason' => $rolledBack['rollback_reason'],
    'dependencies' => $committed['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
