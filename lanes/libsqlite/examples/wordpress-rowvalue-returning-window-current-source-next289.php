<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan;

require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$attempt = "UPDATE wp_options SET status = 'attempt289' WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET status = 'retry289' WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'pending_theme')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute(
    ['wp_options' => $rows],
    [$attempt],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

echo json_encode([
    'status' => $plan['status'],
    'discarded_attempt_window_rowids' => array_column($plan['discarded_attempt_window_rows'], 'current_rowid'),
    'yielded_retry_window_rowids' => array_column($plan['yielded_retry_window_rows'], 'current_rowid'),
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'yielded_after_retry_count' => $plan['yielded_after_retry_count'],
], JSON_PRETTY_PRINT) . PHP_EOL;
