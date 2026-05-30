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
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer189', option_value || ':outer189', bytes + 1) WHERE (blog_id, option_name) NOT BETWEEN (2, 'a') AND (3, 'zzzz') RETURNING * ORDER BY option_id",
    ],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'ignored189', option_value || ':ignored189', bytes + 9) WHERE option_id = 7 RETURNING *",
        "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'pending_theme'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT BETWEEN (2, 'a') AND (3, 'zzzz') AS outside_network ORDER BY option_id LIMIT 4",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry189', option_value || ':retry189', bytes + 5) WHERE (blog_id, option_name) NOT BETWEEN (1, 'a') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'siteurl'), (2, 'home'), (2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch'), (4, 'siteurl')) RETURNING * ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rowvalue-not-between-returning-star-rollback-retry-next189');
    assert($plan['outer_yielded_returning_count'] === 5);
    assert($plan['suppressed_by_rollback_count'] === 4);
    assert($plan['yielded_after_retry_count'] === 8);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 5, 6, 7, 8, 9, 10]);
    echo "application-rowvalue-not-between-savepoint-current-source-next189 self-test passed\n";
    return;
}

echo json_encode([
    'applicationUse' => 'Model copied wp_options cleanup where row-value NOT BETWEEN updates return full rows, an inner DELETE NOT IN (VALUES ...) stream is rolled back, and the retry reads the current source preserved by the outer savepoint.',
    'status' => $plan['status'],
    'outerReturning' => $plan['outer_yielded_returning_count'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
