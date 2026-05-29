<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 23, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 9, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 27, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 28, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 11, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => null, 'bytes' => 6, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeValuesRetrySavepointBatch(
    ['wp_options' => $rows],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id",
        "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':outer', 'outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, option_name, status, option_value, bytes",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, '_transient_feed')) RETURNING option_id, blog_id, option_name",
        "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate') WHERE (blog_id, option_name) = (3, 'orphaned_cache') RETURNING option_id, blog_id, option_name, status",
    ],
    [
        "UPDATE wp_options SET (option_name, status, option_value) = (option_name || ':retry', 'retry', option_value || ':retry') WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-rollback-transaction-savepoint',
    'wordpressUse' => 'A copied wp_options import that hits UPDATE OR ROLLBACK inside a cleanup savepoint discards outer and savepoint RETURNING rows, restores the transaction image, and retries UPDATE/DELETE RETURNING from the restored current source.',
    'status' => $plan['status'],
    'rollbackReason' => $plan['rollback_reason'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'retryReturningIds' => array_merge(
        array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
        array_column($plan['yielded_returning'][1]['rows'], 'option_id'),
    ),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution and transaction/savepoint current-source modeling',
];

if (
    $summary['status'] !== 'transaction-rolled-back-retried'
    || $summary['discardedReturningCount'] !== 4
    || $summary['retryReturningIds'] !== [8, 9, 3, 4, 7]
    || $summary['finalOptionIds'] !== [1, 2, 5, 6, 8, 9]
) {
    fwrite(STDERR, "wordpress-rowvalue-rollback-transaction-savepoint self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "wordpress-rowvalue-rollback-transaction-savepoint self-test passed\n";
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
