<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 2, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 3, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 4, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];
$meta = [
    ['meta_id' => 201, 'meta_option_id' => 1, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 202, 'meta_option_id' => 1, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 11],
    ['meta_id' => 203, 'meta_option_id' => 2, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 204, 'meta_option_id' => 3, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 205, 'meta_option_id' => 4, 'meta_key' => 'network_retry', 'meta_value' => 'network_plugin', 'priority' => 40],
    ['meta_id' => 206, 'meta_option_id' => 4, 'meta_key' => 'network_retry', 'meta_value' => 'network_plugin', 'priority' => 41],
];

$attempt = "UPDATE wp_options SET status = 'attemptbounded' WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, status ORDER BY option_id";
$retry = "UPDATE wp_options SET status = 'retrybounded' WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, status ORDER BY option_id DESC";
$delete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_retry' ORDER BY priority ASC LIMIT 1) RETURNING option_id, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attempt],
    [$retry, $delete],
    [['blog_id', 'option_name']],
);

if (($plan['attempt_statements'][0]['selected_ids'] ?? null) !== [1, 2]) {
    throw new RuntimeException('Expected DISTINCT attempt tuple source to collapse duplicate optionmeta rows');
}
if (($plan['retry_statements'][0]['selected_ids'] ?? null) !== [3, 2]) {
    throw new RuntimeException('Expected retry to read the savepoint image with descending distinct tuples');
}
if (in_array(4, array_column($plan['current_source_tables']['wp_options'], 'option_id'), true)) {
    throw new RuntimeException('Expected duplicate network_retry metadata to delete one option row once');
}

echo json_encode([
    'status' => $plan['status'],
    'attempt_selected' => $plan['attempt_statements'][0]['selected_ids'],
    'retry_selected' => $plan['retry_statements'][0]['selected_ids'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'yielded_after_retry_count' => $plan['yielded_after_retry_count'],
], JSON_PRETTY_PRINT) . PHP_EOL;
