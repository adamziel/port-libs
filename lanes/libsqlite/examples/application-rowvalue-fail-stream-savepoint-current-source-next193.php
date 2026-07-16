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
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 11, 'blog_id' => 1, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'installed', 'bytes' => 31, 'option_value' => 'plugin-existing'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStreamSavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer193', option_value || ':outer193', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
    [
        "UPDATE OR FAIL wp_options SET (blog_id, status, option_value, bytes) = (1, 'fail193', option_value || ':fail193', bytes + 10) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (3, option_name) AS moved_key ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry193', option_value || ':retry193', bytes + 5) WHERE (blog_id, option_name) IS (3, 'rewrite_rules') OR (blog_id, option_name) IS (3, 'plugin_batch') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (1, 'plugin_batch')) AS plugin_key ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-fail-stream-savepoint-current-source-next193');
    assert($plan['fail_yielded_before_conflict_count'] === 1);
    assert($plan['suppressed_by_rollback_count'] === 1);
    assert($plan['yielded_after_retry_count'] === 5);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 8, 9]);
    echo "application-rowvalue-fail-stream-savepoint-current-source-next193 self-test passed\n";
    return;
}

echo json_encode([
    'applicationUse' => 'Model copied wp_options import cleanup where UPDATE OR FAIL yields an early RETURNING row, a later row-value unique conflict triggers ROLLBACK TO, and retry DELETE/UPDATE statements read the restored savepoint source.',
    'status' => $plan['status'],
    'failYieldedBeforeConflict' => $plan['fail_yielded_before_conflict_count'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'failedConflict' => $plan['failed_conflicts'][0] ?? null,
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
