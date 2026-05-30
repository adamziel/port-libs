<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield309', option_value || ':yield309', bytes + 47) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt309', option_value || ':attempt309', bytes + 17) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry309', option_value || ':retry309', bytes + 41) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next306 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(306, ...$args);
$next307 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(307, ...$args);
$next308 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(308, ...$args);
$next309 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(309, ...$args);

$statuses = [$next306['status'], $next307['status'], $next308['status'], $next309['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next306',
    'rowvalue-update-delete-returning-window-current-source-next307',
    'rowvalue-update-delete-returning-window-current-source-next308',
    'rowvalue-update-delete-returning-window-current-source-next309',
]);
assert($next309['next309_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next306-309',
    'candidateStatuses' => $statuses,
    'next306Handoff' => $next306['next306_handoff']['next306_handoff'],
    'next306AfterReadyRange' => $next306['next306_handoff']['after_ready_range'],
    'next307SourceAudit' => $next307['next307_source_audit']['next307_source_audit'],
    'next307PreservesCurrentSource' => $next307['next307_source_audit']['retry_rows_preserve_current_source'],
    'next308Preflight' => $next308['next308_preflight']['next308_preflight'],
    'next308KeepsThroughputHigh' => $next308['next308_preflight']['keeps_libsqlite_throughput_high'],
    'next309Final' => $next309['next309_final']['next309_final'],
    'next309Ready' => $next309['next309_ready'],
    'applicationUse' => 'Copied wp_options imports can validate the next306-309 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next302-305 handoff while preserving independent preflight throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next306-309 self-test passed\n";
    return;
}

return $summary;
