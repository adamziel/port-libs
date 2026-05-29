<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeAbortReturningSavepointBatch(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('staged', option_value || ':staged', bytes + 2) WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':abort', option_value || ':abort', bytes + 100) WHERE option_id IN (8, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retried', option_value || ':retried', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-abort-savepoint-current-source-next170',
    'wordpressUse' => 'Model a copied wp_options import savepoint where a row-value UPDATE OR ABORT conflict discards only the failed statement, preserves earlier UPDATE/DELETE RETURNING changes, retries against that current source, and then releases the savepoint.',
    'status' => $plan['status'],
    'abortedStatement' => $plan['aborted_statement']['ordinal'] ?? null,
    'yieldedBeforeAbort' => $plan['yielded_returning_count_before_abort'],
    'retryReturnedIds' => array_column($plan['retry_returning'][0]['rows'] ?? [], 'option_id'),
    'afterAbortIds' => array_column($plan['current_source_after_abort_tables']['wp_options'], 'option_id'),
    'finalStatuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
    'finalValues' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP row-value UPDATE/DELETE RETURNING execution with savepoint current-source retry modeling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'aborted-statement-preserved-savepoint'
        || $summary['abortedStatement'] !== 2
        || $summary['yieldedBeforeAbort'] !== 4
        || $summary['retryReturnedIds'] !== [7, 8]
        || $summary['afterAbortIds'] !== [1, 2, 5, 6, 7, 8, 9]
        || ($summary['finalValues'][7] ?? null) !== 'theme:staged:retried'
        || ($summary['finalValues'][8] ?? null) !== 'rules:retried'
    ) {
        fwrite(STDERR, "unexpected row-value ABORT savepoint summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
