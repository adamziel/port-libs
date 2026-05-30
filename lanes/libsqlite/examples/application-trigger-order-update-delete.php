<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteTriggerOrderPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'value' => 'feed'],
    ['option_id' => 3, 'option_name' => 'plugin_settings', 'autoload' => 'yes', 'bytes' => 48, 'value' => '{"enabled":true}'],
];

$triggers = [
    [
        'name' => 'wp_options_before_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'wp_options',
        'of' => ['value'],
        'values' => ['option_id' => 'old.option_id', 'before_value' => 'old.value', 'after_value' => 'new.value'],
    ],
    [
        'name' => 'wp_options_after_delete',
        'timing' => 'after',
        'event' => 'delete',
        'table' => 'wp_options',
        'values' => ['option_id' => 'old.option_id', 'deleted_name' => 'old.option_name'],
    ],
];

$updated = SQLiteUpdateDeleteTriggerOrderPlan::updateRows(
    $options,
    ['value' => static fn (array $row): string => $row['value'] . ':checked'],
    static fn (array $row): bool => $row['option_name'] === 'plugin_settings',
    $triggers,
);

$deleted = SQLiteUpdateDeleteTriggerOrderPlan::deleteRows(
    $updated['rows'],
    static fn (array $row): bool => str_starts_with($row['option_name'], '_transient_'),
    $triggers,
);

echo json_encode([
    'updatedAudit' => $updated['audit'],
    'deletedAudit' => $deleted['audit'],
    'remainingOptions' => array_column($deleted['rows'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
