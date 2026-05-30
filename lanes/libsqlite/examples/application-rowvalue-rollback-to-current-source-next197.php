<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$outer = "UPDATE wp_options SET (status, option_value, bytes) = ('outer197', option_value || ':outer197', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$innerUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('inner197', option_value || ':inner197', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retry = "UPDATE wp_options SET (status, option_value, bytes) = ('retry197', option_value || ':retry197', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToInnerSavepointRetry(
    ['wp_options' => $rows],
    [$outer],
    [$innerDelete, $innerUpdate],
    [$retry],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-rollback-to-current-source-next197');
    assert($plan['rollback_to_inner_savepoint'] === true);
    assert($plan['inner_rolled_back_returning_count'] === 3);
    assert($plan['yielded_after_retry_count'] === 2);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5, 6, 7]);
    assert(array_column($plan['yielded_after_retry_returning'][0]['rows'], 'option_value') === ['theme:outer197:retry197', 'cache:retry197']);
    echo "application-rowvalue-rollback-to-current-source-next197 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'outer_returning_count' => $plan['outer_yielded_returning_count'],
    'rolled_back_returning_count' => $plan['inner_rolled_back_returning_count'],
    'retry_returning_count' => $plan['yielded_after_retry_count'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
