<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('staged', option_value || ':stage', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) = (1, '_transient_feed') RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', 'duplicate', option_value || ':bad') WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retried', option_value || ':retry', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-abort-rollback-retry-savepoint',
    'wordpressUse' => 'A copied wp_options import can catch an UPDATE OR ABORT duplicate row-value key statement, keep earlier savepoint UPDATE/DELETE RETURNING effects, then retry cleanup from that current source before release.',
    'status' => $plan['status'],
    'statementAborted' => $plan['statement_aborted'],
    'transactionRolledBack' => $plan['transaction_rolled_back'],
    'yieldedBeforeAbortCount' => $plan['yielded_before_abort_count'],
    'abortedStatementReturningCount' => $plan['aborted_statement_returning_count'],
    'retryReturningCount' => $plan['yielded_returning_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalRowSevenValue' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id')[7],
    'dependencyClosure' => 'no new support component needed; this reuses the row-value UPDATE/DELETE RETURNING executor and savepoint current-source model',
];

if (
    $summary['status'] !== 'statement-aborted-savepoint-preserved-retried-current-source'
    || $summary['statementAborted'] !== true
    || $summary['transactionRolledBack'] !== false
    || $summary['yieldedBeforeAbortCount'] !== 3
    || $summary['abortedStatementReturningCount'] !== 0
    || $summary['retryReturningCount'] !== 3
    || $summary['finalOptionIds'] !== [1, 7, 9]
    || $summary['finalRowSevenValue'] !== 'theme:stage:retry'
) {
    fwrite(STDERR, "wordpress-rowvalue-abort-rollback-retry-savepoint self-test failed\n");
    exit(1);
}

echo "wordpress-rowvalue-abort-rollback-retry-savepoint self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
