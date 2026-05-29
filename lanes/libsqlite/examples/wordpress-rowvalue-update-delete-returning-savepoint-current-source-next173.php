<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', option_name || ':attempt', option_value || ':attempt', bytes + 100) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (3, 'plugin_batch') AS moved_key ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('restored-retry', option_value || ':retry', bytes + 1) WHERE (blog_id, status) IS NOT DISTINCT FROM (3, 'queued') RETURNING option_id, status, option_value, bytes, (blog_id, status) IS (3, 'restored-retry') AS retried_tuple ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS deleted_tuple ORDER BY option_id LIMIT 1";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInPredicateRetrySavepointBatch(
    ['wp_options' => $rows],
    [$failSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'fail-stream-rolled-back-retried-current-source-next173');
    assert($plan['discarded_returning_count'] === 1);
    assert($plan['yielded_returning_count'] === 3);
    assert(array_column($plan['yielded_returning'][0]['rows'], 'option_id') === [8, 9]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 4, 5, 6, 7, 8, 9]);
    echo "OK\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'rolledBackToSavepoint' => $plan['rolled_back_to_savepoint'],
    'failedConflict' => $plan['failed_conflict'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'retryReturnedOptionIds' => array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
    'deletedTransientIds' => array_column($plan['yielded_returning'][1]['rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
