<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewUpsertReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_import_view', 'wp_option_import_view', 0, 'CREATE VIEW wp_option_import_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_view_io_insert', 'wp_option_import_view', 0, 'CREATE TRIGGER wp_option_import_view_io_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_name; END', 3),
];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];
$mapping = [
    'import_id' => 'option_id',
    'name' => 'option_name',
    'value' => 'option_value',
    'autoload_flag' => 'autoload',
];
$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];
$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$triggers = [[
    'name' => 'wp_options_bu_rekey_meta',
    'timing' => 'before',
    'event' => 'update',
    'action' => 'update-child',
    'match' => 'old.option_id',
    'set_child_key' => 'new.option_id',
    'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
]];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
];

$committed = SQLiteViewUpsertReturningSavepointPlan::execute(
    $schema,
    'wp_option_import_view_io_insert',
    $parents,
    $children,
    [
        ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://new.test', 'autoload_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
    ],
    $mapping,
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggers,
    null,
    $returning,
    'wp_view_import',
);

$rolledBack = SQLiteViewUpsertReturningSavepointPlan::execute(
    $schema,
    'wp_option_import_view_io_insert',
    $parents,
    $children,
    [
        ['import_id' => 201, 'name' => 'siteurl', 'value' => 'first', 'autoload_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'home', 'value' => 'bad', 'autoload_flag' => 'yes'],
    ],
    $mapping,
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false],
    [[
        'name' => 'wp_options_bu_orphan_home',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'when' => ['new.option_name', '=', 'home'],
        'match' => 'old.option_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.option_name'],
    ]],
    null,
    $returning,
    'wp_view_import',
);

echo json_encode([
    'committedNames' => array_column($committed['parent'], 'option_name'),
    'committedReturning' => $committed['view_returning_rows'],
    'committedChanges' => $committed['changes'],
    'rollbackNames' => array_column($rolledBack['parent'], 'option_name'),
    'rollbackReturningCommitted' => $rolledBack['returning_rows'],
    'rollbackDiagnosticReturning' => $rolledBack['view_returning_rows'],
    'rollbackReason' => $rolledBack['rollback_reason'],
    'dependencies' => $committed['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
