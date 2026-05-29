<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$stageSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage', 'stage', option_value || ':stage', bytes + 1) WHERE option_id IN (5, 6) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$rollbackSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value) = (2, 'siteurl', option_name || ':rollback', option_value || ':rollback') WHERE option_id = 5 RETURNING option_id, blog_id, option_name, status, option_value";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry164', 'retry', option_value || ':retry164', bytes + 10) WHERE option_id IN (5, 6) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNullInequalityRetrySavepointBatch(
    ['wp_options' => $rows],
    [$stageSql, $rollbackSql, $retryDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert(str_starts_with($plan['status'], 'transaction-rolled-back-retried-current-source-'));
    assert($plan['transaction_rolled_back'] === true);
    assert($plan['discarded_returning_count'] === 2);
    assert(array_column($plan['rollback_current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5, 6]);
    assert(array_column($plan['retry_statements'][0]['source_rows'], 'option_name') === ['pending_theme', 'rewrite_rules']);
    assert(array_column($plan['yielded_returning'][0]['rows'], 'status') === ['retry', 'retry']);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 4, 5, 6]);
    echo "wordpress-rowvalue-rollback-retry-savepoint self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rowvalue-rollback-retry-savepoint',
    'status' => 'transaction-rolled-back-retried-current-source',
    'rollbackStatementOrdinal' => $plan['rollback_statement_ordinal'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'currentOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'wordpressUse' => 'Copied wp_options import batches using UPDATE OR ROLLBACK with row-value assignments discard speculative RETURNING rows, restore the transaction image, then retry UPDATE/DELETE RETURNING cleanup from the restored current source.',
    'dependencyClosure' => 'no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING conflict detection and current-source savepoint modeling',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
