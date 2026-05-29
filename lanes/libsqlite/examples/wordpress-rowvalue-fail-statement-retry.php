<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

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

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('prefailStatementRetry', option_value || ':prefailStatementRetry', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_migrated' ELSE 'siteurl' END, 'failfailStatementRetry', option_value || ':failfailStatementRetry', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retryfailStatementRetry', option_value || ':retryfailStatementRetry', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme_migrated'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-or-fail-savepoint-current-source-fail-statement-retry',
    'wordpressUse' => 'Copied wp_options repair can keep successful row-value UPDATE RETURNING rows from an UPDATE OR FAIL statement, suppress the conflicting row RETURNING stream, and retry cleanup from that preserved current source.',
    'status' => $plan['status'],
    'failPreservedReturningCount' => $plan['fail_preserved_yielded_count'],
    'suppressedByFailCount' => $plan['suppressed_by_fail_count'],
    'yieldedAfterRetryCount' => $plan['yielded_after_retry_count'],
    'finalIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this reuses native PHP UPDATE/DELETE RETURNING, row-value predicates, CASE assignment expressions, and unique conflict handling',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'rowvalue-update-delete-returning-or-fail-current-source-fail-statement-retry');
    assert($summary['failPreservedReturningCount'] === 1);
    assert($summary['suppressedByFailCount'] === 1);
    assert($summary['yieldedAfterRetryCount'] === 4);
    assert($summary['finalIds'] === [1, 2, 7, 8]);
    echo "wordpress-rowvalue-or-fail-savepoint-current-source-fail-statement-retry self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
