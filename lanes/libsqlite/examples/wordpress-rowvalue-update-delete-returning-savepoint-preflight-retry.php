<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$stageSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':draft', 'draft', option_value || ':draft', bytes + 1) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry', 'retry', option_value || ':retry', bytes + 10) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executePreflightRetrySavepointBatch(
    ['wp_options' => $rows],
    [$stageSql, $discardDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released-after-rollback-to-retry');
    assert($plan['discarded_returning_count'] === 4);
    assert(array_column($plan['rollback_to_current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5]);
    assert(array_column($plan['retry_statements'][0]['source_rows'], 'option_name') === ['pending_theme', 'rewrite_rules']);
    assert(array_column($plan['yielded_returning'][0]['rows'], 'status') === ['retry', 'retry']);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 4, 5]);
    echo "wordpress-rowvalue-update-delete-returning-savepoint-preflight-retry self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-preflight-retry',
    'status' => $plan['status'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturning' => $plan['yielded_returning'],
    'currentOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'wordpressUse' => 'Copied wp_options import batches can roll back speculative row-value UPDATE/DELETE RETURNING cleanup, retry from the savepoint image, and only yield RETURNING rows from the released retry source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
