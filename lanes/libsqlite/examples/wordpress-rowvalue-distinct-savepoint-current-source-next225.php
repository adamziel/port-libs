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
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 12, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];
$meta = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 11],
    ['meta_id' => 103, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 104, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 21],
    ['meta_id' => 105, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 106, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 31],
    ['meta_id' => 107, 'meta_option_id' => 12, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 40],
    ['meta_id' => 108, 'meta_option_id' => 12, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 41],
];

$attempt = "UPDATE wp_options SET status = 'attempt225' WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id ASC LIMIT 2) RETURNING option_id, status ORDER BY option_id";
$retry = "UPDATE wp_options SET status = 'retry225' WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id DESC LIMIT 2) RETURNING option_id, status ORDER BY option_id DESC";
$delete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY meta_option_id DESC LIMIT 1) RETURNING option_id, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctSubquerySavepointRollback(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attempt],
    [$retry, $delete],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-distinct-savepoint-current-source-next225',
    'status' => $plan['status'],
    'attempt_selected' => $plan['attempt_statements'][0]['selected_ids'],
    'retry_selected' => $plan['retry_statements'][0]['selected_ids'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'yielded_after_retry_count' => $plan['yielded_after_retry_count'],
    'wordpressUse' => 'Copied wp_options imports can collapse duplicate staging metadata tuples with SELECT DISTINCT before UPDATE/DELETE RETURNING, roll back the attempted stream to a savepoint image, then retry from the current source without duplicate tuple side effects.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP row-value UPDATE/DELETE RETURNING execution and savepoint current-source modeling',
];

if ($summary['status'] !== 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next225'
    || $summary['attempt_selected'] !== [7, 8]
    || $summary['retry_selected'] !== [9, 8]
    || $summary['final_option_ids'] !== [7, 8, 9]
) {
    fwrite(STDERR, "wordpress-rowvalue-distinct-savepoint-current-source-next225 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
