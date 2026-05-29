<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield317', option_value || ':yield317', bytes + 57) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt317', option_value || ':attempt317', bytes + 23) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry317', option_value || ':retry317', bytes + 47) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next314 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext314(...$args);
$next315 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext315(...$args);
$next316 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext316(...$args);
$next317 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext317(...$args);

$statuses = [$next314['status'], $next315['status'], $next316['status'], $next317['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next314',
    'rowvalue-update-delete-returning-window-current-source-next315',
    'rowvalue-update-delete-returning-window-current-source-next316',
    'rowvalue-update-delete-returning-window-current-source-next317',
]);
assert($next317['next317_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next314-317',
    'candidateStatuses' => $statuses,
    'next314Handoff' => $next314['next314_handoff']['next314_handoff'],
    'next314AfterReadyRange' => $next314['next314_handoff']['after_ready_range'],
    'next315SourceAudit' => $next315['next315_source_audit']['next315_source_audit'],
    'next315PreservesCurrentSource' => $next315['next315_source_audit']['retry_rows_preserve_current_source'],
    'next316Preflight' => $next316['next316_preflight']['next316_preflight'],
    'next316KeepsThroughputHigh' => $next316['next316_preflight']['keeps_libsqlite_throughput_high'],
    'next317Final' => $next317['next317_final']['next317_final'],
    'next317Ready' => $next317['next317_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next314-317 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next310-313 handoff while preserving independent libsqlite preflight throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next314-317 self-test passed\n";
    return;
}

return $summary;
