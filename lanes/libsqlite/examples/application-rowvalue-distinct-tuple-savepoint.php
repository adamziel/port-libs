<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require_once dirname(__DIR__, 3) . '/tools/TestRunner.php';
require_once dirname(__DIR__) . '/src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteRowIdColumn.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

$settings = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'homepage', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://homepage.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
];

$targets = [
    ['target_id' => 1, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'target_key' => 'import_touch', 'priority' => 10],
    ['target_id' => 2, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'target_key' => 'import_touch', 'priority' => 20],
    ['target_id' => 3, 'tenant_id' => 3, 'key_name' => 'route_rules', 'target_key' => 'import_touch', 'priority' => 30],
    ['target_id' => 4, 'tenant_id' => 3, 'key_name' => 'route_rules', 'target_key' => 'import_touch', 'priority' => 40],
    ['target_id' => 5, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'target_key' => 'delete_touch', 'priority' => 50],
    ['target_id' => 6, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'target_key' => 'delete_touch', 'priority' => 60],
    ['target_id' => 7, 'tenant_id' => 3, 'key_name' => 'module_batch', 'target_key' => 'retry_touch', 'priority' => 70],
    ['target_id' => 8, 'tenant_id' => 3, 'key_name' => 'module_batch', 'target_key' => 'retry_touch', 'priority' => 80],
];

$tables = ['app_settings' => $settings, 'app_setting_targets' => $targets];
$unique = [['tenant_id', 'key_name']];
$attemptUpdate = "UPDATE app_settings SET (status, key_value, bytes) = ('attempttuple', key_value || ':attempttuple', bytes + 2) WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'import_touch' ORDER BY priority LIMIT -1 OFFSET 1) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";
$attemptDelete = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'delete_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";
$retryUpdate = "UPDATE app_settings SET (status, key_value, bytes) = ('retrytuple', key_value || ':retrytuple', bytes + 1) WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'import_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";
$retryDelete = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'retry_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback(
    $tables,
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    $unique,
);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['suppressed_returning_count'] !== 2) {
        fwrite(STDERR, "Expected two suppressed attempt rows\n");
        exit(1);
    }
    if ($plan['retry_returning_count'] !== 3) {
        fwrite(STDERR, "Expected three retry rows\n");
        exit(1);
    }
    if (array_column($plan['retry_rows'], 'setting_id') !== [7, 8, 9]) {
        fwrite(STDERR, "Unexpected retry row ids\n");
        exit(1);
    }
    if ($plan['rollback_current_source_tables'] !== $plan['savepoint_image_tables']) {
        fwrite(STDERR, "Rollback did not restore the savepoint image\n");
        exit(1);
    }
}

echo json_encode([
    'status' => $plan['status'],
    'suppressed_returning_count' => $plan['suppressed_returning_count'],
    'retry_returning_count' => $plan['retry_returning_count'],
    'retry_ids' => array_column($plan['retry_rows'], 'setting_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
