<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];
$outer = "UPDATE wp_options SET (status, option_value, bytes) = ('outer204', option_value || ':outer204', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, status ORDER BY option_id";
$savepoint = "UPDATE wp_options SET (status, option_value, bytes) = ('saved204', option_value || ':saved204', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, status ORDER BY option_id";
$rollback = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'rollback204', option_value || ':rollback204', bytes + 10) WHERE option_id IN (8, 9) RETURNING option_id, status ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry204', option_value || ':retry204', bytes + 5) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, status, option_value ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, option_name ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry(
    $tables,
    [$outer],
    [$savepoint],
    [$rollback],
    [$retryUpdate, $retryDelete],
    $unique,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['transaction_rolled_back'] === true);
    assert($plan['savepoint_invalidated_by_rollback'] === true);
    assert($plan['suppressed_by_transaction_rollback_count'] === 4);
    assert($plan['yielded_after_retry_count'] === 5);
    assert(array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[1] === 'live');
    assert(array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[8] === 'retry204');
    assert(array_values(array_intersect([3, 4, 11], array_column($plan['current_source_tables']['wp_options'], 'option_id'))) === []);

    echo "wordpress rowvalue rollback savepoint next204 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'transaction_rolled_back' => $plan['transaction_rolled_back'],
    'suppressed_returning' => $plan['suppressed_by_transaction_rollback_count'],
    'retry_returning' => $plan['yielded_after_retry_count'],
    'remaining_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
