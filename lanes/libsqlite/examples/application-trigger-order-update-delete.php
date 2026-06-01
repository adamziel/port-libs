<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteTriggerOrderPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['setting_id' => 2, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'bytes' => 12, 'value' => 'feed'],
    ['setting_id' => 3, 'key_name' => 'module_settings', 'load_policy' => 'yes', 'bytes' => 48, 'value' => '{"enabled":true}'],
];

$triggers = [
    [
        'name' => 'app_settings_before_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'app_settings',
        'of' => ['value'],
        'values' => ['setting_id' => 'old.setting_id', 'before_value' => 'old.value', 'after_value' => 'new.value'],
    ],
    [
        'name' => 'app_settings_after_delete',
        'timing' => 'after',
        'event' => 'delete',
        'table' => 'app_settings',
        'values' => ['setting_id' => 'old.setting_id', 'deleted_name' => 'old.key_name'],
    ],
];

$updated = SQLiteUpdateDeleteTriggerOrderPlan::updateRows(
    $settings,
    ['value' => static fn (array $row): string => $row['value'] . ':checked'],
    static fn (array $row): bool => $row['key_name'] === 'module_settings',
    $triggers,
);

$deleted = SQLiteUpdateDeleteTriggerOrderPlan::deleteRows(
    $updated['rows'],
    static fn (array $row): bool => str_starts_with($row['key_name'], 'cache_'),
    $triggers,
);

echo json_encode([
    'updatedAudit' => $updated['audit'],
    'deletedAudit' => $deleted['audit'],
    'remainingSettings' => array_column($deleted['rows'], 'key_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
