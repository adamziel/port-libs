<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer186', option_value || ':outer186', bytes + 1) WHERE option_id = 4 RETURNING option_id, option_name, status"],
    ["UPDATE wp_options SET (status, option_value) = ('attempt186', option_value || ':attempt186') WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, status, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 2"],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, (blog_id, option_name) IN () AS empty_in, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 1",
        "UPDATE wp_options SET (status, option_value) = ('retry186', option_value || ':retry186') WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, status ORDER BY option_id LIMIT 1",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'empty-rowvalue-in-savepoint-current-source-retry-next186');
    assert($plan['attempt_yielded_before_rollback_count'] === 2);
    assert($plan['suppressed_by_rollback_count'] === 2);
    assert($plan['yielded_after_retry_count'] === 2);
    assert($plan['current_source_tables']['wp_options'][0]['option_id'] === 2);
    echo "wordpress-rowvalue-empty-in-savepoint-current-source-next186 self-test passed\n";
    return;
}

echo json_encode([
    'wordpressUse' => 'Copied wp_options import cleanup preserves SQLite empty row-value IN semantics during UPDATE/DELETE RETURNING retries: IN () selects no rows, NOT IN () selects rows including NULL tuples, and ROLLBACK TO suppresses attempted RETURNING rows.',
    'status' => $plan['status'],
    'attemptSuppressed' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'remainingOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
