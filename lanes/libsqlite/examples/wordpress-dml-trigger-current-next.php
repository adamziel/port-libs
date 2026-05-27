<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no'],
];

$triggers = [
    [
        'name' => 'wp_options_after_insert',
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'wp_options',
        'values' => ['phase' => 'inserted', 'rowid' => 'new.option_id', 'name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_after_update',
        'timing' => 'after',
        'event' => 'update',
        'table' => 'wp_options',
        'of' => ['option_value'],
        'values' => ['phase' => 'updated', 'rowid' => 'old.option_id', 'name' => 'old.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_after_delete',
        'timing' => 'after',
        'event' => 'delete',
        'table' => 'wp_options',
        'values' => ['phase' => 'deleted', 'rowid' => 'old.option_id', 'name' => 'old.option_name', 'value' => 'old.option_value'],
    ],
];

$insert = SQLiteDmlTriggerCurrentNextPlan::insertRows($rows, [
    ['option_id' => null, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes'],
], $triggers);

$update = SQLiteDmlTriggerCurrentNextPlan::updateRows(
    $insert['rows'],
    ['option_value' => 'Example Site Updated'],
    static fn (array $row): bool => $row['option_name'] === 'blogname',
    $triggers,
);

$delete = SQLiteDmlTriggerCurrentNextPlan::deleteRows(
    $update['rows'],
    static fn (array $row): bool => $row['autoload'] === 'no',
    $triggers,
);

echo json_encode([
    'remaining' => array_column($delete['rows'], 'option_name'),
    'audit' => array_merge($insert['audit'], $update['audit'], $delete['audit']),
], JSON_PRETTY_PRINT) . PHP_EOL;
