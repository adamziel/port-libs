<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
    ['setting_id' => 10, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
    ['setting_id' => 11, 'tenant_id' => 4, 'key_name' => 'landing_page', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 31, 'key_value' => 'https://four-landing.test'],
];

$meta = [
    ['target_id' => 101, 'target_setting_id' => 7, 'target_batch' => 'activation_batch', 'target_key_name' => 'pending_profile', 'priority' => 30],
    ['target_id' => 102, 'target_setting_id' => 8, 'target_batch' => 'activation_batch', 'target_key_name' => 'route_rules', 'priority' => 50],
    ['target_id' => 103, 'target_setting_id' => 9, 'target_batch' => 'activation_batch', 'target_key_name' => 'module_batch', 'priority' => 40],
    ['target_id' => 108, 'target_setting_id' => 10, 'target_batch' => 'archive_batch', 'target_key_name' => 'base_url', 'priority' => 90],
    ['target_id' => 109, 'target_setting_id' => 11, 'target_batch' => 'archive_batch', 'target_key_name' => 'landing_page', 'priority' => 70],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(
    ['app_settings' => $rows, 'app_setting_targets' => $meta],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('attempt214', key_value || ':attempt214', bytes + 4) WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 2) RETURNING setting_id, key_name, status ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('retry214', key_value || ':retry214', bytes + 2) WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 1, 2) RETURNING setting_id, key_name, status ORDER BY setting_id",
        "DELETE FROM app_settings WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'archive_batch' ORDER BY priority DESC LIMIT 1) RETURNING setting_id, key_name ORDER BY setting_id",
    ],
    [['tenant_id', 'key_name']],
);

$summary = [
    'status' => $plan['status'],
    'discardedAttemptSettingIds' => array_column($plan['discarded_attempt_returning'][0]['rows'], 'setting_id'),
    'retrySelectedIds' => $plan['retry_statements'][0]['selected_ids'],
    'deletedArchiveIds' => array_column($plan['yielded_after_retry_returning'][1]['rows'], 'setting_id'),
    'finalSettingIds' => array_column($plan['current_source_tables']['app_settings'], 'setting_id'),
    'finalValues' => array_column($plan['current_source_tables']['app_settings'], 'key_value', 'setting_id'),
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'status' => 'rowvalue-update-delete-returning-ordered-subquery-savepoint-current-source-next214',
        'discardedAttemptSettingIds' => [8, 9],
        'retrySelectedIds' => [7, 9],
        'deletedArchiveIds' => [10],
        'finalSettingIds' => [7, 8, 9, 11],
    ];
    foreach ($expected as $key => $value) {
        if (($summary[$key] ?? null) !== $value) {
            throw new RuntimeException("Unexpected {$key} in row-value ordered subquery savepoint example");
        }
    }
    echo "application-rowvalue-ordered-subquery-savepoint-retry self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
