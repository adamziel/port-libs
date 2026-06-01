<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteAffinityComparison.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteRowIdColumn.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_layout', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'theme'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'routing_rules', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
    ['setting_id' => 11, 'tenant_id' => 4, 'key_name' => 'stale_cache', 'status' => 'stale', 'bytes' => 14, 'key_value' => 'cache'],
    ['setting_id' => 12, 'tenant_id' => 4, 'key_name' => 'tenant_module', 'status' => 'queued', 'bytes' => 16, 'key_value' => 'network'],
];
$meta = [
    ['meta_id' => 1, 'meta_setting_id' => 7, 'meta_key' => 'retry_batch', 'meta_value' => 'pending_layout', 'priority' => 10],
    ['meta_id' => 2, 'meta_setting_id' => 8, 'meta_key' => 'retry_batch', 'meta_value' => 'routing_rules', 'priority' => 20],
    ['meta_id' => 3, 'meta_setting_id' => 9, 'meta_key' => 'retry_batch', 'meta_value' => 'module_batch', 'priority' => 30],
    ['meta_id' => 4, 'meta_setting_id' => 11, 'meta_key' => 'retry_batch', 'meta_value' => 'stale_cache', 'priority' => 40],
    ['meta_id' => 5, 'meta_setting_id' => 12, 'meta_key' => 'retry_cleanup', 'meta_value' => 'tenant_module', 'priority' => 50],
];

$attempt = "UPDATE app_settings SET (status, key_value, bytes) = ('attempt234', key_value || ':attempt234', bytes + 1) WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT 2) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id";
$retryUpdate = "UPDATE app_settings SET (status, key_value, bytes) = ('retry234', key_value || ':retry234', bytes + 2) WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT -1) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id";
$retryDelete = "DELETE FROM app_settings WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_cleanup' ORDER BY priority ASC) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(
    ['app_settings' => $rows, 'app_setting_targets' => $meta],
    [$attempt],
    [$retryUpdate, $retryDelete],
    [['tenant_id', 'key_name']],
);

if (in_array('--self-test', $argv, true)) {
    $ids = array_column($plan['window_rows'], 'setting_id');
    if ($plan['status'] !== 'rowvalue-update-delete-returning-window-current-source-next234') {
        fwrite(STDERR, "unexpected status\n");
        exit(1);
    }
    if ($ids !== [7, 8, 9, 11, 12]) {
        fwrite(STDERR, "unexpected window row ids: " . json_encode($ids) . "\n");
        exit(1);
    }
    if (($plan['window_partition_summary']['3']['row_ids'] ?? null) !== [8, 9]) {
        fwrite(STDERR, "unexpected tenant 3 partition\n");
        exit(1);
    }
    if (in_array(12, array_column($plan['current_source_tables']['app_settings'], 'setting_id'), true)) {
        fwrite(STDERR, "tenant module row was not deleted after retry\n");
        exit(1);
    }
    echo "application-rowvalue-returning-window-current-source-next234 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'yielded_returning_count' => $plan['yielded_returning_count'],
    'window_row_ids' => array_column($plan['window_rows'], 'setting_id'),
    'partition_summary' => $plan['window_partition_summary'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
