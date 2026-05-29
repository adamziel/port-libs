<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executePreFailRollbackRetry(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer208', option_value || ':outer208', bytes + 1) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id"],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('pre208', option_value || ':pre208', bytes + 2) WHERE (blog_id, option_name) IN (VALUES (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
    "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (5, 'shared_fail', 'fail208', option_value || ':fail208', bytes + 20) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((5, 'shared_fail')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry208', option_value || ':retry208', bytes + 5) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'status' => $plan['status'],
    'or_fail_returning_count' => $plan['or_fail_returning_count'],
    'failed_conflict' => [
        'row_id' => $plan['failed_conflict']['row_id'] ?? null,
        'conflicting_row_ids' => $plan['failed_conflict']['conflicting_row_ids'] ?? [],
    ],
    'retry_yielded_count_before_rollback' => $plan['retry_yielded_count_before_rollback'],
    'rollback_restored_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'rollback_restored_statuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next208') {
        throw new RuntimeException('unexpected next208 status');
    }
    if ($summary['or_fail_returning_count'] !== 1 || $summary['failed_conflict']['row_id'] !== 8) {
        throw new RuntimeException('OR FAIL partial RETURNING boundary was not preserved');
    }
    if ($summary['rollback_restored_statuses'][7] !== 'queued' || in_array(3, $summary['rollback_restored_option_ids'], true) !== true) {
        throw new RuntimeException('ROLLBACK TO savepoint did not restore the savepoint image');
    }

    echo "wordpress-rowvalue-fail-savepoint-current-source-next208 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
