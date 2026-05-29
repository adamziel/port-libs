<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield276', option_value || ':yield276', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt276', option_value || ':attempt276', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry276', option_value || ':retry276', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next273 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext273(...$args);
$next274 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext274(...$args);
$next275 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext275(...$args);
$next276 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext276(...$args);

$statuses = [$next273['status'], $next274['status'], $next275['status'], $next276['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next273',
    'rowvalue-update-delete-returning-window-current-source-next274',
    'rowvalue-update-delete-returning-window-current-source-next275',
    'rowvalue-update-delete-returning-window-current-source-next276',
]);
assert($next276['after_current_handoff_ready_next276'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next273-276-after-current',
    'candidateStatuses' => $statuses,
    'next273Admission' => $next273['current_source_admission_next273']['current_source_admission_next273'],
    'next274Balance' => $next274['returning_balance_next274']['returning_balance_receipt_next274'],
    'next275Package' => $next275['next_source_package_next275']['next_source_package_next275'],
    'next276Handoff' => $next276['after_current_handoff_next276']['after_current_handoff_receipt_next276'],
    'next276Ready' => $next276['after_current_handoff_ready_next276'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next273-276 after-current row-value UPDATE/DELETE RETURNING handoff as admission, balance, next-source package, and final handoff receipts.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next273-276-after-current self-test passed\n";
    return;
}

return $summary;
