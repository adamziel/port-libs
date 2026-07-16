<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 40, 'option_value' => 'a:0:{}'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id"],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner', option_value || ':inner', bytes + 5) WHERE option_id IN (10) RETURNING option_id, option_name, status, option_value, bytes",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('after', option_value || ':after', bytes + 2) WHERE (status, option_name) IN (('outer', 'pending_theme'), ('outer', 'orphaned_cache')) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-nested-savepoint-rollback-batch',
    'applicationUse' => 'A copied wp_options import can roll back an inner cleanup savepoint, discard its UPDATE/DELETE RETURNING rows, preserve the outer staged option rows, and continue the next statement from that restored current source.',
    'status' => $plan['status'],
    'outerReturned' => array_column($plan['outer_returning'][0]['rows'], 'option_id'),
    'discardedInnerReturningCount' => $plan['discarded_inner_returning_count'],
    'afterRollbackReturned' => array_column($plan['after_rollback_returning'][0]['rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes the native PHP row-value UPDATE/DELETE RETURNING executor with lane-local nested savepoint current-source modeling',
];

if (
    $summary['status'] !== 'inner-rolled-back-outer-current-source-preserved'
    || $summary['outerReturned'] !== [7, 8]
    || $summary['discardedInnerReturningCount'] !== 3
    || $summary['afterRollbackReturned'] !== [7, 8]
    || $summary['finalOptionIds'] !== [1, 4, 7, 8, 10]
) {
    fwrite(STDERR, "application-rowvalue-nested-savepoint-rollback-batch self-test failed\n");
    exit(1);
}

echo "application-rowvalue-nested-savepoint-rollback-batch self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
