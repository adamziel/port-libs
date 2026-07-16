<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bytes' => 31, 'option_value' => 'a:0:{}'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 10, 'option_value' => 'network-feed'],
];

$attemptUpdate = "UPDATE wp_options SET status = 'attempt195', option_value = option_value || ':attempt195', match_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) WHERE NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) RETURNING option_id, option_name, status, option_value, match_flag ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) AND autoload = 'no' RETURNING option_id, option_name ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET status = 'retry195', option_value = option_value || ':retry195', match_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) WHERE NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) RETURNING option_id, option_name, status, option_value, match_flag ORDER BY option_id";
$retryDelete = $attemptDelete;

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    'app_settings_rowvalue_unary_not_distinct_next195',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['attempt_returning_count'] === 3);
    assert($plan['suppressed_by_rollback_count'] === 3);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4]);
    assert($plan['current_source_tables']['wp_options'][0]['option_value'] === 'https://old.test:retry195');
    echo "application-rowvalue-unary-not-distinct-savepoint-current-source-next195 self-test passed\n";
    return;
}

echo json_encode([
    'status' => 'rowvalue-unary-not-distinct-savepoint-current-source-next195',
    'attempt_returning' => $plan['attempt_returning_count'],
    'suppressed_returning' => $plan['suppressed_by_rollback_count'],
    'retry_returning' => $plan['yielded_after_retry_count'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
