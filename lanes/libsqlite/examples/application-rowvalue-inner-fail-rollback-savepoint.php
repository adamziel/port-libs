<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

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

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerFailRollbackSavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner', option_value || ':inner', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_inner_migrated' ELSE 'siteurl' END, 'fail', option_value || ':fail', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-inner-fail-rollback-savepoint',
    'status' => $plan['status'],
    'applicationUse' => 'A copied wp_options import can keep outer option-row changes, roll back an inner plugin-batch savepoint after UPDATE OR FAIL, suppress all inner RETURNING rows, and retry cleanup from the outer current source without ext/sqlite.',
    'outer_yielded_count' => $plan['outer_yielded_count'],
    'total_suppressed_by_inner_rollback_count' => $plan['total_suppressed_by_inner_rollback_count'],
    'retry_returning_count' => $plan['retry_returning_count'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-inner-fail-rollback-current-source'
    || $summary['outer_yielded_count'] !== 3
    || $summary['total_suppressed_by_inner_rollback_count'] !== 5
    || $summary['retry_returning_count'] !== 5
    || $summary['final_option_ids'] !== [1, 2, 7, 8, 9]
) {
    fwrite(STDERR, "application-rowvalue-inner-fail-rollback-savepoint self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
