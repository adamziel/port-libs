<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':stage', 'stage', option_value || ':stage', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
        "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', option_name || ':conflict', option_value || ':conflict') WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id DESC",
    ],
    [
        "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':retry', 'retry', option_value || ':retry', bytes + 20) WHERE option_id IN (7, 8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-conflict-savepoint-returning-current-source-next143',
    'wordpressUse' => 'Model a copied wp_options import savepoint where earlier row-value UPDATE/DELETE RETURNING rows are yielded, a later OR ABORT row-value conflict forces ROLLBACK TO the savepoint image, and the retry executes against the restored current source rather than the failed attempted source.',
    'status' => $plan['status'],
    'failedReason' => $plan['failed_statement']['reason'] ?? null,
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'retryReturningCount' => $plan['retry_returning_count'],
    'preRollbackOptionIds' => array_column($plan['pre_rollback_current_source_tables']['wp_options'], 'option_id'),
    'rollbackOptionIds' => array_column($plan['rollback_current_source_tables']['wp_options'], 'option_id'),
    'retryOptionIds' => array_column($plan['post_retry_current_source_tables']['wp_options'], 'option_id'),
    'retryStatuses' => array_column($plan['post_retry_current_source_tables']['wp_options'], 'status', 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP row-value UPDATE/DELETE RETURNING execution, unique conflict handling, and savepoint current-source modeling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'rolled-back-to-savepoint-then-retried'
        || $summary['discardedReturningCount'] !== 4
        || $summary['retryReturningCount'] !== 5
        || $summary['preRollbackOptionIds'] !== [1, 2, 5, 6, 7, 8, 9]
        || $summary['rollbackOptionIds'] !== [1, 2, 3, 4, 5, 6, 7, 8, 9]
        || $summary['retryOptionIds'] !== [1, 2, 5, 6, 7, 8, 9]
        || ($summary['retryStatuses'][9] ?? null) !== 'retry'
    ) {
        fwrite(STDERR, "unexpected row-value conflict retry savepoint summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
