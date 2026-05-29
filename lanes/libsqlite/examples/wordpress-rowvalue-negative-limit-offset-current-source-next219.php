<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 2, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 3, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 4, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];
$meta = [
    ['meta_id' => 101, 'meta_option_id' => 1, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 3, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 20],
    ['meta_id' => 103, 'meta_option_id' => 2, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 30],
    ['meta_id' => 104, 'meta_option_id' => 4, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 35],
];

$attempt = "UPDATE wp_options SET status = 'attempt219' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT -1 OFFSET 1) RETURNING option_id, status ORDER BY option_id";
$retry = "UPDATE wp_options SET status = 'retry219' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT -1 OFFSET 1) RETURNING option_id, status ORDER BY option_id";
$delete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT -1 OFFSET 0) RETURNING option_id, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegativeLimitOffsetSubquerySavepointRetry(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attempt],
    [$retry, $delete],
    [['blog_id', 'option_name']],
);

echo json_encode([
    'status' => $plan['status'],
    'attempt_selected' => $plan['attempt_statements'][0]['selected_ids'],
    'retry_selected' => $plan['retry_statements'][0]['selected_ids'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'yielded_after_retry_count' => $plan['yielded_after_retry_count'],
], JSON_PRETTY_PRINT) . PHP_EOL;
