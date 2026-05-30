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
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => null, 'bytes' => 28, 'option_value' => 'https://network.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$attempt = "UPDATE wp_options SET status = 'attempt191', option_value = option_value || ':attempt191', keep_flag = (blog_id, option_name) IN (VALUES (1, 'siteurl'), (2, 'siteurl')), outside_flag = (blog_id, option_name) NOT IN ((1, 'home'), (3, 'rewrite_rules')), range_flag = (blog_id, bytes) BETWEEN (1, 10) AND (2, 30), same_flag = (blog_id, status) IS (1, 'live'), distinct_flag = (blog_id, status) IS DISTINCT FROM (2, NULL), unknown_flag = (blog_id, status) = (2, NULL) WHERE autoload = 'yes' RETURNING option_id, option_name, status, keep_flag, outside_flag, range_flag, same_flag, distinct_flag, unknown_flag ORDER BY option_id";
$delete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, 'pending_theme')) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$retry = str_replace("'attempt191'", "'retry191'", $attempt);

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [$attempt, $delete],
    [$retry, $delete],
    'app_settings_rowvalue_assignment_predicates_next191',
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['attempt_returning_count'] === 6);
    assert($plan['suppressed_by_rollback_count'] === 6);
    assert($plan['yielded_after_retry_count'] === 6);
    assert(array_column($plan['yielded_after_retry_returning'][0]['rows'], 'keep_flag') === [1, 0, 1, 0]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 4, 6]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id')[1] === 'https://old.test:retry191');
    echo "application-rowvalue-assignment-savepoint-current-source-next191 self-test passed\n";
    return;
}

echo json_encode([
    'applicationUse' => 'Model copied wp_options cleanup where row-value predicates are assigned into flag columns, speculative RETURNING rows are rolled back, and retry starts from the restored savepoint image.',
    'savepoint' => $plan['savepoint'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'retryKeepFlags' => array_column($plan['yielded_after_retry_returning'][0]['rows'], 'keep_flag'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
