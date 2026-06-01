<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'timeout'],
];
$targets = [
    ['target_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'yield', 'priority' => 10],
    ['target_id' => 2, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'yield', 'priority' => 20],
    ['target_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'attempt', 'priority' => 30],
    ['target_id' => 4, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'attempt', 'priority' => 40],
    ['target_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'retry', 'priority' => 50],
    ['target_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'retry', 'priority' => 60],
    ['target_id' => 7, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'action' => 'retry', 'priority' => 70],
    ['target_id' => 8, 'blog_id' => 4, 'option_name' => '_transient_timeout_feed', 'action' => 'retry_delete', 'priority' => 80],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield232', option_value || ':yield232', bytes + 3) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY priority LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt232', option_value || ':attempt232', bytes + 5) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt' ORDER BY priority) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry232', option_value || ':retry232', bytes + 1) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry' ORDER BY priority LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry_delete' ORDER BY priority LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan(
    ['wp_options' => $rows, 'wp_import_targets' => $targets],
    [$yieldUpdate],
    [$attemptUpdate],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
    rowIdColumn: 'option_id',
);

echo json_encode([
    'scenario' => 'application-rowvalue-window-current-source-next232',
    'applicationUse' => 'During copied wp_options imports, row-value target tuples may drive UPDATE/DELETE RETURNING batches, failed attempts roll back to the savepoint image, and retry RETURNING rows keep stable window-style ordinals for progress reporting before release.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retryIds' => $plan['window_retry_ids_after_release_next232'],
    'retryRowNumbers' => $plan['window_retry_row_numbers_next232'],
    'partitionRowNumbers' => $plan['window_retry_partition_numbers_next232'],
    'currentSourceOrder' => $plan['current_source_window_order_next232'],
    'rowCounts' => $plan['row_counts'],
    'dependencyClosure' => $plan['dependency_closure_next232'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
