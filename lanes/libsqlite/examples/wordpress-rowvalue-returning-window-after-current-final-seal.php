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
$next285 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentReceipt(...$args);
$next286 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentLedger(...$args);
$next287 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentWindowCoverage(...$args);
$next288 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentFinalSeal(...$args);

$statuses = [$next285['status'], $next286['status'], $next287['status'], $next288['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next285',
    'rowvalue-update-delete-returning-window-current-source-next286',
    'rowvalue-update-delete-returning-window-current-source-next287',
    'rowvalue-update-delete-returning-window-current-source-next288',
]);
assert($next288['after_current_ready_next288'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-after-current-final-seal',
    'candidateStatuses' => $statuses,
    'receipt' => $next285['after_current_receipt_next285']['after_current_receipt_next285'],
    'ledger' => $next286['after_current_ledger_next286']['after_current_ledger_next286'],
    'windowCoverage' => $next287['after_current_window_next287']['after_current_window_next287'],
    'retryWindowRows' => $next287['after_current_window_next287']['retry_window_rows'],
    'finalSeal' => $next288['after_current_next288']['after_current_next288'],
    'finalReady' => $next288['after_current_ready_next288'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared final after-current seal row-value UPDATE/DELETE RETURNING window current-source handoff as receipt, ledger, retry-window, and final seal metadata.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-after-current-final-seal self-test passed\n";
    return;
}

return $summary;
