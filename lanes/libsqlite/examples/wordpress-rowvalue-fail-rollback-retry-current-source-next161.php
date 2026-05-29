<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161(
    ['wp_options' => $rows],
    [
        "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', option_name || ':fail', option_value || ':fail', bytes + 100) WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry', 'retry', option_value || ':retry', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-fail-rollback-retry-current-source-next161',
    'wordpressUse' => 'A copied wp_options import can yield one UPDATE OR FAIL RETURNING row, hit a later row-value unique conflict, roll back to the import savepoint, and retry UPDATE/DELETE RETURNING against the restored current source.',
    'status' => $plan['status'],
    'discardedReturningIds' => array_column($plan['discarded_returning'][0]['rows'], 'option_id'),
    'retryReturningIds' => array_merge(
        array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
        array_column($plan['yielded_returning'][1]['rows'], 'option_id'),
    ),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes the native PHP row-value UPDATE/DELETE RETURNING executor with savepoint current-source retry modeling',
];

if (
    $summary['status'] !== 'failed-rolled-back-to-savepoint-retried'
    || $summary['discardedReturningIds'] !== [7]
    || $summary['retryReturningIds'] !== [7, 8, 3, 4]
    || $summary['finalOptionIds'] !== [1, 5, 7, 8]
) {
    fwrite(STDERR, "wordpress-rowvalue-fail-rollback-retry-current-source-next161 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "wordpress-rowvalue-fail-rollback-retry-current-source-next161 self-test passed\n";
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
