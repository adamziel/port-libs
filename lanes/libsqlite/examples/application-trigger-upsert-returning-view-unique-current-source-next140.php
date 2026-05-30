<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer UNIQUE, option_name text UNIQUE, option_value text, autoload text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_import_view', 'wp_option_import_view', 0, 'CREATE VIEW wp_option_import_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_io_insert', 'wp_option_import_view', 0, 'CREATE TRIGGER wp_option_import_io_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_id, option_name, option_value; END', 3),
];

$plan = SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
    $schema,
    'wp_option_import_io_insert',
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'touched' => 'seed'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'touched' => 'seed'],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'touched' => 'seed'],
    ],
    [
        ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
        ['meta_id' => 13, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
    ],
    [
        ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://first.test', 'autoload_flag' => 'yes'],
        ['import_id' => 2, 'name' => 'blogname', 'value' => 'collides-with-home-id', 'autoload_flag' => 'yes'],
        ['import_id' => 105, 'name' => 'never_seen', 'value' => 'nope', 'autoload_flag' => 'no'],
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
            'values' => ['name' => 'new.option_name', 'new_key' => 'new.option_id'],
        ],
    ],
    null,
    [
        ['expr' => 'new.option_id', 'as' => 'id'],
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'excluded.option_id', 'as' => 'incomingId'],
    ],
    'next140',
    [['option_name'], ['option_id']],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-view-unique-current-source-next140',
    'applicationUse' => 'Preview a copied wp_options import routed through an INSTEAD OF view trigger, where secondary UNIQUE option_id conflicts roll back previously yielded UPSERT RETURNING rows.',
    'rolledBack' => $plan['rolled_back'],
    'rolledBackAtOrdinal' => $plan['rolled_back_at_ordinal'],
    'rollbackReasonContainsUnique' => str_contains((string) $plan['rollback_reason'], 'unique constraint conflict'),
    'changes' => $plan['changes'],
    'parentIdsAfterRollback' => array_column($plan['parent'], 'option_id'),
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
