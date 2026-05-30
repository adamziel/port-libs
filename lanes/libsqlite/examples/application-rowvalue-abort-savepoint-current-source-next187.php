<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 9, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 27, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 28, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 11, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => null, 'bytes' => 6, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeAbortSavepointRetry(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value) = ('outer187', option_value || ':outer187') WHERE (blog_id, option_name) IN (VALUES (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (2, '_transient_feed')) RETURNING option_id, blog_id, option_name",
        "UPDATE wp_options SET (status, option_value) = ('savepoint187', option_value || ':savepoint187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value",
        "UPDATE OR ABORT wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status",
    ],
    [
        "UPDATE wp_options SET (status, option_value) = ('retry187', option_value || ':retry187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (2, '_transient_feed')) RETURNING option_id, blog_id, option_name",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-abort-savepoint-current-source-next187',
    'applicationUse' => 'Copied wp_options cleanup can keep outer rewrite-rule work while an OR ABORT row-value savepoint batch is rolled back, discards attempted RETURNING rows, and retries from the savepoint image.',
    'status' => $plan['status'],
    'rollbackReason' => $plan['rollback_reason'],
    'outerReturningCount' => $plan['outer_returning_count'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalRewriteStatus' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[8],
    'finalOrphanStatus' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[9],
    'dependencyClosure' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution plus current-source savepoint modeling',
];

if (
    $summary['status'] !== 'savepoint-rolled-back-retried-current-source-next187'
    || $summary['outerReturningCount'] !== 2
    || $summary['discardedReturningCount'] !== 2
    || $summary['yieldedReturningCount'] !== 2
    || $summary['finalOptionIds'] !== [1, 2, 3, 5, 6, 8, 9]
    || $summary['finalRewriteStatus'] !== 'outer187'
    || $summary['finalOrphanStatus'] !== 'retry187'
) {
    fwrite(STDERR, "application-rowvalue-abort-savepoint-current-source-next187 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "application-rowvalue-abort-savepoint-current-source-next187 self-test passed\n";
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
