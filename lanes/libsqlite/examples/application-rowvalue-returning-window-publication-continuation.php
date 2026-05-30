<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield357', option_value || ':yield357', bytes + 83) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt357', option_value || ':attempt357', bytes + 47) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry357', option_value || ':retry357', bytes + 71) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$planFor = static fn (int $step): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterReadyPublicationStep(
    $step,
    ...array_merge($args, ['wp_options_rowvalue_window_current_next' . $step]),
);
$next350 = $planFor(350);
$next351 = $planFor(351);
$next352 = $planFor(352);
$next353 = $planFor(353);
$next354 = $planFor(354);
$next355 = $planFor(355);
$next356 = $planFor(356);
$next357 = $planFor(357);

$statuses = [
    $next350['status'],
    $next351['status'],
    $next352['status'],
    $next353['status'],
    $next354['status'],
    $next355['status'],
    $next356['status'],
    $next357['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next350',
    'rowvalue-update-delete-returning-window-current-source-next351',
    'rowvalue-update-delete-returning-window-current-source-next352',
    'rowvalue-update-delete-returning-window-current-source-next353',
    'rowvalue-update-delete-returning-window-current-source-next354',
    'rowvalue-update-delete-returning-window-current-source-next355',
    'rowvalue-update-delete-returning-window-current-source-next356',
    'rowvalue-update-delete-returning-window-current-source-next357',
]);
assert($next353['next353_ready'] === true);
assert($next357['next357_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-publication-continuation',
    'candidateStatuses' => $statuses,
    'next350Handoff' => $next350['next350_handoff']['next350_handoff'],
    'next350AfterReadyRange' => $next350['next350_handoff']['after_ready_range'],
    'next351SourceAudit' => $next351['next351_source_audit']['next351_source_audit'],
    'next351PreservesCurrentSource' => $next351['next351_source_audit']['retry_rows_preserve_current_source'],
    'next352Preflight' => $next352['next352_preflight']['next352_preflight'],
    'next352KeepsThroughputHigh' => $next352['next352_preflight']['keeps_libsqlite_throughput_high'],
    'next353Final' => $next353['next353_final']['next353_final'],
    'next353Ready' => $next353['next353_ready'],
    'next354Handoff' => $next354['next354_handoff']['next354_handoff'],
    'next354AfterReadyRange' => $next354['next354_handoff']['after_ready_range'],
    'next355SourceAudit' => $next355['next355_source_audit']['next355_source_audit'],
    'next355PreservesCurrentSource' => $next355['next355_source_audit']['retry_rows_preserve_current_source'],
    'next356Preflight' => $next356['next356_preflight']['next356_preflight'],
    'next356KeepsThroughputHigh' => $next356['next356_preflight']['keeps_libsqlite_throughput_high'],
    'next357Final' => $next357['next357_final']['next357_final'],
    'next357Ready' => $next357['next357_ready'],
    'applicationUse' => 'Copied wp_options imports can validate the next350-357 row-value UPDATE/DELETE RETURNING window current-source continuation after the merged next342-349 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-publication-continuation self-test passed\n";
    return;
}

return $summary;
