<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$meta = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 5],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 104, 'meta_option_id' => 10, 'meta_key' => 'migration_batch', 'meta_value' => 'siteurl', 'priority' => 40],
    ['meta_id' => 105, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 1],
    ['meta_id' => 106, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 2],
    ['meta_id' => 107, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 9],
];

$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt215', option_value || ':attempt215', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry215', option_value || ':retry215', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-subquery-limit-savepoint-retry',
    'wordpressUse' => 'Model copied wp_options cleanup where row-value UPDATE/DELETE RETURNING reads ordered and limited metadata subqueries, rolls the attempt back to a savepoint image, and retries against the restored current source.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'attemptSelectedIds' => [$plan['attempt_statements'][0]['selected_ids'], $plan['attempt_statements'][1]['selected_ids']],
    'retrySelectedIds' => [$plan['retry_statements'][0]['selected_ids'], $plan['retry_statements'][1]['selected_ids']],
    'discardedAttemptReturningCount' => $plan['discarded_attempt_returning_count'],
    'yieldedAfterRetryCount' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, ordered subquery tuple lists, and savepoint current-source modeling',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'rowvalue-update-delete-returning-subquery-limit-savepoint-current-source-next215');
    assert($summary['attemptSelectedIds'] === [[8, 9], [1, 2, 7, 8, 9, 10]]);
    assert($summary['retrySelectedIds'] === [[8, 9, 10], [10]]);
    assert($summary['finalOptionIds'] === [1, 2, 3, 4, 7, 8, 9]);
    echo "wordpress-rowvalue-subquery-limit-savepoint-retry self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
