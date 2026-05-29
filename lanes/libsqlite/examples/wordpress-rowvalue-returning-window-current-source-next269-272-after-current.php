<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield272', option_value || ':yield272', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt272', option_value || ':attempt272', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry272', option_value || ':retry272', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$next269 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext269(['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]);
$next270 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext270(['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]);
$next271 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext271(['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]);
$next272 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext272(['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]);

$statuses = [$next269['status'], $next270['status'], $next271['status'], $next272['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next269',
    'rowvalue-update-delete-returning-window-current-source-next270',
    'rowvalue-update-delete-returning-window-current-source-next271',
    'rowvalue-update-delete-returning-window-current-source-next272',
]);
assert($next272['after_current_ready_next272'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next269-272-after-current',
    'candidateStatuses' => $statuses,
    'next269ClosureReceipt' => $next269['current_source_closure_next269']['closure_receipt_next269'],
    'next270DeleteGuard' => $next270['delete_returning_guard_next270']['delete_returning_guard_next270'],
    'next271UpdateFence' => $next271['update_returning_fence_next271']['update_returning_fence_next271'],
    'next272AfterCurrentReceipt' => $next272['after_current_summary_next272']['after_current_receipt_next272'],
    'next272Ready' => $next272['after_current_ready_next272'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next269-272 after-current row-value UPDATE/DELETE RETURNING handoff as closure receipts, DELETE guards, UPDATE fences, and final after-current readiness.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next269-272-after-current self-test passed\n";
    return;
}

return $summary;
