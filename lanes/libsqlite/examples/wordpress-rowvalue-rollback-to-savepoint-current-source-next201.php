<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToSavepoint(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer201', option_value || ':outer201', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id"],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('discard201', option_value || ':discard201', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry201', option_value || ':retry201', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-rollback-to-savepoint-current-source-next201',
    'status' => $plan['status'],
    'discardedSavepointReturningCount' => $plan['discarded_savepoint_returning_count'],
    'retryYieldedCount' => $plan['yielded_after_retry_count'],
    'row7Value' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id')[7] ?? null,
    'row3Restored' => array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id')[3] ?? null,
    'row4DeletedByRetry' => !in_array(4, array_column($plan['current_source_tables']['wp_options'], 'option_id'), true),
    'wordpressUse' => 'Model a copied wp_options cleanup where a plugin savepoint emits speculative row-value UPDATE/DELETE RETURNING rows, rolls back to the savepoint image, then retries from the restored current source.',
    'dependencyClosure' => 'no new support component needed; this composes lane-local row-value UPDATE/DELETE RETURNING execution and savepoint current-source rollback modeling',
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-rollback-to-current-source-next201'
    || $summary['discardedSavepointReturningCount'] !== 4
    || $summary['retryYieldedCount'] !== 4
    || $summary['row7Value'] !== 'theme:retry201'
    || $summary['row3Restored'] !== '_transient_feed'
    || $summary['row4DeletedByRetry'] !== true
) {
    fwrite(STDERR, "wordpress-rowvalue-rollback-to-savepoint-current-source-next201 self-test failed\n");
    exit(1);
}

echo "wordpress-rowvalue-rollback-to-savepoint-current-source-next201 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
