<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'transientorFailRetry', 'autoload' => 'no', 'status' => 'existing', 'bytes' => 10, 'option_value' => 'old-transient'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'draft_feed', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 30, 'option_value' => 'draft-feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => 'draft_conflict', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 40, 'option_value' => 'draft-conflict'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'draft_later', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 50, 'option_value' => 'draft-later'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 60, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 70, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'cleanuporFailRetry', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 80, 'option_value' => 'cleanup'],
];

$outer = "UPDATE wp_options SET (status, option_value, bytes) = ('outerorFailRetry', option_value || ':outerorFailRetry', bytes + 1) WHERE (blog_id, option_name) IN ((4, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$fail = "UPDATE OR FAIL wp_options SET (option_name, status, option_value, bytes) = ('transientorFailRetry', 'failorFailRetry', option_value || ':failorFailRetry', bytes + 5) WHERE (blog_id, option_name) IN (VALUES (2, 'draft_feed'), (1, 'draft_conflict'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (2, 'transientorFailRetry') AS prefix_tuple_is ORDER BY option_id";
$retry = "UPDATE wp_options SET (status, option_value, bytes) = ('retryorFailRetry', option_value || ':retryorFailRetry', bytes + 7) WHERE (blog_id, option_name) IN ((2, 'draft_feed'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (option_name, status) IS NOT ('transientorFailRetry', 'failorFailRetry') AS retried_from_image ORDER BY option_id";
$delete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (4, 'cleanuporFailRetry'), (1, 'draft_conflict')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (4, 'cleanuporFailRetry') AS not_cleanup ORDER BY option_id DESC";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry(
    ['wp_options' => $rows],
    [$outer],
    [$fail],
    [$retry, $delete],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    $ids = array_column($plan['current_source_tables']['wp_options'], 'option_id');
    if (
        $plan['status'] !== 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-or-fail-savepoint-retry'
        || $plan['fail_prefix_returning_count'] !== 1
        || $plan['suppressed_by_rollback_count'] !== 1
        || $plan['yielded_after_retry_count'] !== 4
        || in_array(4, $ids, true)
        || in_array(8, $ids, true)
    ) {
        fwrite(STDERR, "wordpress-rowvalue-fail-savepoint-current-source-or-fail-savepoint-retry self-test failed\n");
        exit(1);
    }
    echo "wordpress-rowvalue-fail-savepoint-current-source-or-fail-savepoint-retry self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'failPrefixReturning' => $plan['fail_prefix_returning_count'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'retryReturning' => $plan['yielded_after_retry_count'],
    'failedConflictRow' => $plan['fail_statements'][0]['failed_conflict']['row_id'] ?? null,
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
