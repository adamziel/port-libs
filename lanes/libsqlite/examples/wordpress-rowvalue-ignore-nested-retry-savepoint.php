<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$outerSql = "UPDATE wp_options SET (status, option_value, bytes) = ('outer180', option_value || ':outer180', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$ignoredSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'inner180', option_value || ':inner180', bytes + 3) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('inner180', option_value || ':inner180', bytes + 3) WHERE option_id = 9 RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$discardSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$retrySql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry180', option_value || ':retry180', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreNestedRetrySavepointBatch(
    ['wp_options' => $rows],
    [$outerSql],
    [$ignoredSql, $innerUpdateSql],
    [$discardSql],
    [$retrySql, $discardSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'inner-ignore-rollback-to-retry-current-source');
    assert($plan['inner_yielded_before_rollback_count'] === 1);
    assert($plan['inner_suppressed_by_rollback_count'] === 3);
    assert(array_column($plan['inner_yielded_statements'][0]['ignored_rows'], 'option_id') === [7]);
    assert(array_column($plan['inner_yielded_after_retry_returning'][0]['rows'], 'option_id') === [7, 9]);
    echo "wordpress-rowvalue-ignore-nested-retry-savepoint self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'wordpressUse' => 'Copied wp_options imports can use UPDATE OR IGNORE with row-value assignment inside an inner savepoint, yield no RETURNING row for ignored conflicts, roll back later speculative DELETE RETURNING rows, then retry from the preserved inner current source.',
    'ignoredOptionIds' => array_column($plan['inner_yielded_statements'][0]['ignored_rows'], 'option_id'),
    'innerReturnedBeforeRollback' => array_column($plan['inner_yielded_before_rollback_returning'][1]['rows'], 'option_id'),
    'retryReturnedOptionIds' => array_column($plan['inner_yielded_after_retry_returning'][0]['rows'], 'option_id'),
    'deletedTransientIds' => array_column($plan['inner_yielded_after_retry_returning'][1]['rows'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes native PHP row-value UPDATE OR IGNORE RETURNING, DELETE RETURNING, and inner savepoint current-source retry behavior',
], JSON_PRETTY_PRINT) . PHP_EOL;
