<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 42, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bytes' => 33, 'option_value' => 'network-plugins'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 17, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 29, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => null, 'option_value' => 'cache'],
];

$attemptUpdate = "UPDATE wp_options SET status = 'between198', range_flag = bytes BETWEEN 20 AND 33, outside_flag = bytes NOT BETWEEN 20 AND 33, option_value = option_value || ':between198' WHERE bytes BETWEEN 20 AND 33 RETURNING option_id, option_name, status, range_flag, outside_flag, bytes BETWEEN 20 AND 33 AS returning_between ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE bytes NOT BETWEEN 10 AND 35 AND autoload = 'no' RETURNING option_id, option_name, bytes NOT BETWEEN 10 AND 35 AS outside_range ORDER BY option_id";
$retryUpdate = str_replace('between198', 'retry198', $attemptUpdate);
$retryDelete = $attemptDelete;

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    'wp_options_rowvalue_between_savepoint_next198',
);

$summary = [
    'scenario' => 'application-rowvalue-between-savepoint-current-source-next198',
    'applicationUse' => 'A copied wp_options import can use scalar BETWEEN and NOT BETWEEN in UPDATE/DELETE RETURNING while ROLLBACK TO suppresses attempted rows and retries from the original current-source savepoint.',
    'status' => 'rowvalue-between-returning-rolled-back-retried-next198',
    'savepoint' => $plan['savepoint'],
    'attemptSelected' => array_column($plan['attempt_statements'][0]['source_rows'], 'option_id'),
    'attemptDeleted' => array_column($plan['attempt_statements'][1]['source_rows'], 'option_id'),
    'suppressedReturning' => $plan['suppressed_by_rollback_count'],
    'retryReturning' => $plan['yielded_after_retry_count'],
    'finalIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'nullBytesKept' => in_array(9, array_column($plan['current_source_tables']['wp_options'], 'option_id'), true),
    'dependencyClosure' => 'no new support component needed; this reuses the native PHP UPDATE/DELETE RETURNING executor and current-source savepoint retry model',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['attemptSelected'] !== [1, 2, 6, 8]
        || $summary['attemptDeleted'] !== [3]
        || $summary['suppressedReturning'] !== 5
        || $summary['retryReturning'] !== 5
        || $summary['finalIds'] !== [1, 2, 4, 5, 6, 7, 8, 9]
        || $summary['nullBytesKept'] !== true
    ) {
        fwrite(STDERR, "application-rowvalue-between-savepoint-current-source-next198 self-test failed\n");
        exit(1);
    }
    echo "application-rowvalue-between-savepoint-current-source-next198 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
