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
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN () RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN () AS empty_member ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt188', option_value || ':attempt188', bytes + 1) WHERE (blog_id, option_name) NOT IN () RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) NOT IN () AS outside_empty ORDER BY option_id",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN () AND autoload = 'no' RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN () AS outside_empty ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry188', option_value || ':retry188', bytes + 5) WHERE (blog_id, option_name) NOT IN () AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rowvalue-empty-in-returning-rolled-back-retried-next188');
    assert($plan['attempt_returning_count'] === 6);
    assert($plan['suppressed_by_rollback_count'] === 6);
    assert($plan['yielded_after_retry_count'] === 6);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 6]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id')[1] === 'https://old.test:retry188');
    echo "wordpress-rowvalue-empty-in-savepoint-current-source-next188 self-test passed\n";
    return;
}

echo json_encode([
    'wordpressUse' => 'Model copied wp_options cleanup where an empty row-value IN list selects no rows, NOT IN selects all rows, and ROLLBACK TO suppresses attempted RETURNING rows before retry.',
    'status' => $plan['status'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
