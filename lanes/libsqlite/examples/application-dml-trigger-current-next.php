<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'cache_feed', 'key_value' => 'cached', 'load_policy' => 'no'],
];

$triggers = [
    [
        'name' => 'app_settings_after_insert',
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'app_settings',
        'values' => ['phase' => 'inserted', 'rowid' => 'new.setting_id', 'name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_after_update',
        'timing' => 'after',
        'event' => 'update',
        'table' => 'app_settings',
        'of' => ['key_value'],
        'values' => ['phase' => 'updated', 'rowid' => 'old.setting_id', 'name' => 'old.key_name', 'old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_after_delete',
        'timing' => 'after',
        'event' => 'delete',
        'table' => 'app_settings',
        'values' => ['phase' => 'deleted', 'rowid' => 'old.setting_id', 'name' => 'old.key_name', 'value' => 'old.key_value'],
    ],
];

$insert = SQLiteDmlTriggerCurrentNextPlan::insertRows($rows, [
    ['setting_id' => null, 'key_name' => 'site_title', 'key_value' => 'Example Site', 'load_policy' => 'yes'],
], $triggers);

$update = SQLiteDmlTriggerCurrentNextPlan::updateRows(
    $insert['rows'],
    ['key_value' => 'Example Site Updated'],
    static fn (array $row): bool => $row['key_name'] === 'site_title',
    $triggers,
);

$delete = SQLiteDmlTriggerCurrentNextPlan::deleteRows(
    $update['rows'],
    static fn (array $row): bool => $row['load_policy'] === 'no',
    $triggers,
);

echo json_encode([
    'remaining' => array_column($delete['rows'], 'key_name'),
    'audit' => array_merge($insert['audit'], $update['audit'], $delete['audit']),
], JSON_PRETTY_PRINT) . PHP_EOL;
