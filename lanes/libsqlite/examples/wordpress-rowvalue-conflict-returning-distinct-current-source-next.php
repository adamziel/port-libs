<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 20, 'expected_bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'staged', 'bytes' => 21, 'expected_bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => 'stale', 'bytes' => 12, 'expected_bytes' => 13, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => null, 'bytes' => 13, 'expected_bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 25, 'expected_bytes' => 25.0, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => null, 'expected_status' => null, 'bytes' => 26, 'expected_bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'expected_status' => 'queued', 'bytes' => 7, 'expected_bytes' => '7', 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'expected_status' => 'staged', 'bytes' => 5, 'expected_bytes' => null, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'expected_status' => 'queued', 'bytes' => 9, 'expected_bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, bytes, option_value) = (1, 'siteurl', 'ignored-conflict', bytes + 1, option_value || ':ignored') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 2 RETURNING option_id, blog_id, option_name, status, bytes, (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AS still_drifted",
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, bytes, option_value) = (1, 'siteurl', 'replace-conflict', bytes + 10, option_value || ':replace') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 3 RETURNING option_id, blog_id, option_name, status, bytes, (status, bytes) IS NOT DISTINCT FROM ('replace-conflict', 22) AS replaced_tuple",
        "DELETE FROM wp_options WHERE (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AND autoload = 'yes' RETURNING option_id, option_name, (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AS clean_drift ORDER BY option_id",
        "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', 'abort-conflict', option_value || ':abort') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 7 RETURNING option_id, blog_id, option_name, status, (status, expected_status) IS DISTINCT FROM (NULL, 'queued') AS one_sided_null",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-conflict-returning-distinct-current-source-next',
    'wordpressUse' => 'Model a copied wp_options drift repair where row-value IS DISTINCT FROM selects changed rows, OR IGNORE suppresses conflicting RETURNING rows, OR REPLACE deletes the current conflicting option before RETURNING, clean aligned rows are deleted, and a later OR ABORT leaves the prior current-source prefix intact.',
    'status' => $plan['status'],
    'failedReason' => $plan['failed_statement']['reason'] ?? null,
    'returningCount' => $plan['returning_count'],
    'ignoredCount' => $plan['ignored_count'],
    'deletedConflictCount' => $plan['deleted_conflict_count'],
    'conflictCount' => $plan['conflict_count'],
    'currentOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'currentNames' => array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP row-value DISTINCT predicates, UPDATE/DELETE RETURNING, and unique conflict policy execution',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'stopped-after-conflict'
        || $summary['returningCount'] !== 4
        || $summary['ignoredCount'] !== 1
        || $summary['deletedConflictCount'] !== 1
        || $summary['conflictCount'] !== 3
        || $summary['currentOptionIds'] !== [2, 3, 4, 7, 8]
        || ($summary['currentNames'][3] ?? null) !== 'siteurl'
        || ($summary['currentNames'][7] ?? null) !== 'pending_theme'
    ) {
        fwrite(STDERR, "unexpected row-value conflict DISTINCT current-source summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
