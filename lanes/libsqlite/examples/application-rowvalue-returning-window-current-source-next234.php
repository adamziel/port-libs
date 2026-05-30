<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteAffinityComparison.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = [
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => '_transient_cache', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'cache'],
    ['option_id' => 12, 'blog_id' => 4, 'option_name' => 'network_plugin', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];
$meta = [
    ['meta_id' => 1, 'meta_option_id' => 7, 'meta_key' => 'retry_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 2, 'meta_option_id' => 8, 'meta_key' => 'retry_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 3, 'meta_option_id' => 9, 'meta_key' => 'retry_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 4, 'meta_option_id' => 11, 'meta_key' => 'retry_batch', 'meta_value' => '_transient_cache', 'priority' => 40],
    ['meta_id' => 5, 'meta_option_id' => 12, 'meta_key' => 'retry_cleanup', 'meta_value' => 'network_plugin', 'priority' => 50],
];

$attempt = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt234', option_value || ':attempt234', bytes + 1) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry234', option_value || ':retry234', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT -1) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_cleanup' ORDER BY priority ASC) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attempt],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

if (in_array('--self-test', $argv, true)) {
    $ids = array_column($plan['window_rows'], 'option_id');
    if ($plan['status'] !== 'rowvalue-update-delete-returning-window-current-source-next234') {
        fwrite(STDERR, "unexpected status\n");
        exit(1);
    }
    if ($ids !== [7, 8, 9, 11, 12]) {
        fwrite(STDERR, "unexpected window row ids: " . json_encode($ids) . "\n");
        exit(1);
    }
    if (($plan['window_partition_summary']['3']['rowids'] ?? null) !== [8, 9]) {
        fwrite(STDERR, "unexpected blog 3 partition\n");
        exit(1);
    }
    if (in_array(12, array_column($plan['current_source_tables']['wp_options'], 'option_id'), true)) {
        fwrite(STDERR, "network plugin row was not deleted after retry\n");
        exit(1);
    }
    echo "application-rowvalue-returning-window-current-source-next234 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'yielded_returning_count' => $plan['yielded_returning_count'],
    'window_rowids' => array_column($plan['window_rows'], 'option_id'),
    'partition_summary' => $plan['window_partition_summary'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
