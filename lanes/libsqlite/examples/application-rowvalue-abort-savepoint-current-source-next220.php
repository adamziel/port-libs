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
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeAbortConflictRetry(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('pre220', option_value || ':pre220', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'abort220', option_value || ':abort220', bytes + 9) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry220', option_value || ':retry220', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-abort-savepoint-current-source-next220',
    'applicationUse' => 'A copied wp_options import savepoint hits UPDATE OR ABORT on a row-value unique conflict, suppresses the failing RETURNING stream, preserves prior savepoint changes, and retries UPDATE/DELETE RETURNING from that preserved current source.',
    'status' => $plan['status'],
    'preAbortYielded' => $plan['pre_abort_yielded_count'],
    'suppressedByAbort' => $plan['suppressed_by_abort_count'],
    'retryYielded' => $plan['retry_yielded_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => $plan['dependency_closure_next220'],
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-or-abort-savepoint-current-source-next220'
    || $summary['suppressedByAbort'] !== 2
    || $summary['retryYielded'] !== 3
    || $summary['finalOptionIds'] !== [1, 2, 7, 8]
) {
    fwrite(STDERR, "application-rowvalue-abort-savepoint-current-source-next220 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
