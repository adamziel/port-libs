<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield288', option_value || ':yield288', bytes + 41) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt288', option_value || ':attempt288', bytes + 11) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry288', option_value || ':retry288', bytes + 32) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next285 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext285(...$args);
$next286 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext286(...$args);
$next287 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext287(...$args);
$next288 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext288(...$args);

$statuses = [$next285['status'], $next286['status'], $next287['status'], $next288['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next285',
    'rowvalue-update-delete-returning-window-current-source-next286',
    'rowvalue-update-delete-returning-window-current-source-next287',
    'rowvalue-update-delete-returning-window-current-source-next288',
]);
assert($next288['after_current_ready_next288'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next285-288-after-current',
    'candidateStatuses' => $statuses,
    'next285Receipt' => $next285['after_current_receipt_next285']['after_current_receipt_next285'],
    'next286Ledger' => $next286['after_current_ledger_next286']['after_current_ledger_next286'],
    'next287Window' => $next287['after_current_window_next287']['after_current_window_next287'],
    'next287RetryWindowRows' => $next287['after_current_window_next287']['retry_window_rows'],
    'next288Seal' => $next288['after_current_next288']['after_current_next288'],
    'next288Ready' => $next288['after_current_ready_next288'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next285-288 after-current row-value UPDATE/DELETE RETURNING window current-source handoff as receipt, ledger, retry-window, and final seal metadata.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next285-288-after-current self-test passed\n";
    return;
}

return $summary;
