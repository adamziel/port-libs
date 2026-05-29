<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield349', option_value || ':yield349', bytes + 79) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt349', option_value || ':attempt349', bytes + 43) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry349', option_value || ':retry349', bytes + 67) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next342 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext342(...$args);
$next343 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext343(...$args);
$next344 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext344(...$args);
$next345 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext345(...$args);
$next346 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext346(...$args);
$next347 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext347(...$args);
$next348 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext348(...$args);
$next349 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext349(...$args);

$statuses = [
    $next342['status'],
    $next343['status'],
    $next344['status'],
    $next345['status'],
    $next346['status'],
    $next347['status'],
    $next348['status'],
    $next349['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next342',
    'rowvalue-update-delete-returning-window-current-source-next343',
    'rowvalue-update-delete-returning-window-current-source-next344',
    'rowvalue-update-delete-returning-window-current-source-next345',
    'rowvalue-update-delete-returning-window-current-source-next346',
    'rowvalue-update-delete-returning-window-current-source-next347',
    'rowvalue-update-delete-returning-window-current-source-next348',
    'rowvalue-update-delete-returning-window-current-source-next349',
]);
assert($next345['next345_ready'] === true);
assert($next349['next349_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next342-349',
    'candidateStatuses' => $statuses,
    'next342Handoff' => $next342['next342_handoff']['next342_handoff'],
    'next342AfterReadyRange' => $next342['next342_handoff']['after_ready_range'],
    'next343SourceAudit' => $next343['next343_source_audit']['next343_source_audit'],
    'next343PreservesCurrentSource' => $next343['next343_source_audit']['retry_rows_preserve_current_source'],
    'next344Preflight' => $next344['next344_preflight']['next344_preflight'],
    'next344KeepsThroughputHigh' => $next344['next344_preflight']['keeps_libsqlite_throughput_high'],
    'next345Final' => $next345['next345_final']['next345_final'],
    'next345Ready' => $next345['next345_ready'],
    'next346Handoff' => $next346['next346_handoff']['next346_handoff'],
    'next346AfterReadyRange' => $next346['next346_handoff']['after_ready_range'],
    'next347SourceAudit' => $next347['next347_source_audit']['next347_source_audit'],
    'next347PreservesCurrentSource' => $next347['next347_source_audit']['retry_rows_preserve_current_source'],
    'next348Preflight' => $next348['next348_preflight']['next348_preflight'],
    'next348KeepsThroughputHigh' => $next348['next348_preflight']['keeps_libsqlite_throughput_high'],
    'next349Final' => $next349['next349_final']['next349_final'],
    'next349Ready' => $next349['next349_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next342-349 row-value UPDATE/DELETE RETURNING window current-source continuation after the merged next334-341 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next342-349 self-test passed\n";
    return;
}

return $summary;
