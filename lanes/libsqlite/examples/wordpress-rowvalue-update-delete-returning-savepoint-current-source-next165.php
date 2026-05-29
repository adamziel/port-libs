<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':ignored', option_value || ':ignored', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('continued', option_value || ':continued', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source-next165',
    'wordpressUse' => 'A copied wp_options import can ignore duplicate row-value key updates without yielding RETURNING rows, keep the savepoint current source active, then continue cleanup DELETE RETURNING and follow-up UPDATE RETURNING statements.',
    'status' => $plan['status'],
    'ignoredReturningCount' => $plan['ignored_returning_count'],
    'deletedIds' => array_column($plan['yielded_returning'][1]['rows'], 'option_id'),
    'continuedIds' => array_column($plan['yielded_returning'][2]['rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes the native PHP row-value UPDATE/DELETE RETURNING executor with savepoint current-source release behavior',
];

if (
    $summary['status'] !== 'released-after-rowvalue-ignore-conflicts'
    || $summary['ignoredReturningCount'] !== 2
    || $summary['deletedIds'] !== [3, 4]
    || $summary['continuedIds'] !== [7, 9]
    || $summary['finalOptionIds'] !== [1, 7, 8, 9]
) {
    fwrite(STDERR, "wordpress-rowvalue-update-delete-returning-savepoint-current-source-next165 self-test failed\n");
    exit(1);
}

echo "wordpress-rowvalue-update-delete-returning-savepoint-current-source-next165 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
