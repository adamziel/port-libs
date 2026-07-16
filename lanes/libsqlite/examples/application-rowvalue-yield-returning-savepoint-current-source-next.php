<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
        ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
    ],
];

$plan = SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute(
    $tables,
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('yield223', option_value || ':yield223', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt223', option_value || ':attempt223', bytes + 5) WHERE (status, option_name) IN (('yield223', 'pending_theme'), ('yield223', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry223', option_value || ':retry223', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-yield-returning-savepoint-current-source',
    'applicationUse' => 'Model a copied wp_options import where a client already consumed row-value UPDATE/DELETE RETURNING rows before ROLLBACK TO; later attempted rows are suppressed and retry starts from the savepoint image.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'suppressedReturningCount' => $plan['suppressed_returning_count'],
    'retryReturningCount' => $plan['retry_returning_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-yield-savepoint-current-source'
    || $summary['yieldedReturningCount'] !== 3
    || $summary['suppressedReturningCount'] !== 3
    || $summary['retryReturningCount'] !== 4
    || $summary['finalOptionIds'] !== [1, 3, 4, 5]
) {
    fwrite(STDERR, "application-rowvalue-yield-returning-savepoint-current-source self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
