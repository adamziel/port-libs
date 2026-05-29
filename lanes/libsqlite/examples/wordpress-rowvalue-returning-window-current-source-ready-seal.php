<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield284', option_value || ':yield284', bytes + 37) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt284', option_value || ':attempt284', bytes + 9) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry284', option_value || ':retry284', bytes + 28) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next281 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceReceipt(...$args);
$next282 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowLedger(...$args);
$next283 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentWindowReceipt(...$args);
$next284 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceReadySeal(...$args);

$statuses = [$next281['status'], $next282['status'], $next283['status'], $next284['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next281',
    'rowvalue-update-delete-returning-window-current-source-next282',
    'rowvalue-update-delete-returning-window-current-source-next283',
    'rowvalue-update-delete-returning-window-current-source-next284',
]);
assert($next284['current_source_ready_next284'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-ready-seal',
    'candidateStatuses' => $statuses,
    'receipt' => $next281['current_source_receipt_next281']['current_source_receipt_next281'],
    'ledger' => $next282['returning_window_ledger_next282']['returning_window_ledger_next282'],
    'windowReceipt' => $next283['after_current_window_receipt_next283']['after_current_window_receipt_next283'],
    'retryWindowRows' => $next283['after_current_window_receipt_next283']['retry_window_rows'],
    'readySeal' => $next284['current_source_next284']['current_source_next284'],
    'ready' => $next284['current_source_ready_next284'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared current-source ready seal row-value UPDATE/DELETE RETURNING window current-source handoff as receipt, ledger, retry-window, and final seal metadata.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-ready-seal self-test passed\n";
    return;
}

return $summary;
