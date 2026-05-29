<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield325', option_value || ':yield325', bytes + 67) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt325', option_value || ':attempt325', bytes + 31) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry325', option_value || ':retry325', bytes + 59) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next322 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext322(...$args);
$next323 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext323(...$args);
$next324 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext324(...$args);
$next325 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext325(...$args);

$statuses = [$next322['status'], $next323['status'], $next324['status'], $next325['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next322',
    'rowvalue-update-delete-returning-window-current-source-next323',
    'rowvalue-update-delete-returning-window-current-source-next324',
    'rowvalue-update-delete-returning-window-current-source-next325',
]);
assert($next325['next325_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next322-325',
    'candidateStatuses' => $statuses,
    'next322Handoff' => $next322['next322_handoff']['next322_handoff'],
    'next322AfterReadyRange' => $next322['next322_handoff']['after_ready_range'],
    'next323SourceAudit' => $next323['next323_source_audit']['next323_source_audit'],
    'next323PreservesCurrentSource' => $next323['next323_source_audit']['retry_rows_preserve_current_source'],
    'next324Preflight' => $next324['next324_preflight']['next324_preflight'],
    'next324KeepsThroughputHigh' => $next324['next324_preflight']['keeps_libsqlite_throughput_high'],
    'next325Final' => $next325['next325_final']['next325_final'],
    'next325Ready' => $next325['next325_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next322-325 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next318-321 handoff while preserving independent libsqlite preflight throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next322-325 self-test passed\n";
    return;
}

return $summary;
