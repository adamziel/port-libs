<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreRollbackRetry(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('ignore210', option_value || ':ignore210', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('ignore210', 'pending_theme') AS pending_touched ORDER BY option_id",
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'ignore210_conflict', option_value || ':ignore210_conflict', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN ((3, 'plugin_batch')) AS plugin_delete ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry210', option_value || ':retry210', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-ignore-rollback-current-source-next210',
    'applicationUse' => 'A copied wp_options import savepoint attempts row-value UPDATE OR IGNORE cleanup, suppresses RETURNING for the conflicting siteurl row, rolls back the savepoint, then retries from the original current source.',
    'status' => $plan['status'],
    'ignoredIds' => array_column($plan['ignored_rows_before_rollback'], 'option_id'),
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'retryYielded' => $plan['yielded_after_retry_count'],
    'finalIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => $plan['dependency_closure_next210'],
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-ignore-rollback-current-source-next210'
    || $summary['ignoredIds'] !== [8]
    || $summary['suppressedByRollback'] !== 4
    || $summary['retryYielded'] !== 5
) {
    fwrite(STDERR, "application-rowvalue-ignore-rollback-current-source-next210 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
