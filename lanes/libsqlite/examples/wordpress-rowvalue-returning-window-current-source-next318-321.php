<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield321', option_value || ':yield321', bytes + 61) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt321', option_value || ':attempt321', bytes + 29) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry321', option_value || ':retry321', bytes + 53) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next318 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(318, ...$args);
$next319 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(319, ...$args);
$next320 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(320, ...$args);
$next321 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(321, ...$args);

$statuses = [$next318['status'], $next319['status'], $next320['status'], $next321['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next318',
    'rowvalue-update-delete-returning-window-current-source-next319',
    'rowvalue-update-delete-returning-window-current-source-next320',
    'rowvalue-update-delete-returning-window-current-source-next321',
]);
assert($next321['next321_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next318-321',
    'candidateStatuses' => $statuses,
    'next318Handoff' => $next318['next318_handoff']['next318_handoff'],
    'next318AfterReadyRange' => $next318['next318_handoff']['after_ready_range'],
    'next319SourceAudit' => $next319['next319_source_audit']['next319_source_audit'],
    'next319PreservesCurrentSource' => $next319['next319_source_audit']['retry_rows_preserve_current_source'],
    'next320Preflight' => $next320['next320_preflight']['next320_preflight'],
    'next320KeepsThroughputHigh' => $next320['next320_preflight']['keeps_libsqlite_throughput_high'],
    'next321Final' => $next321['next321_final']['next321_final'],
    'next321Ready' => $next321['next321_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next318-321 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next314-317 handoff while preserving independent libsqlite preflight throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next318-321 self-test passed\n";
    return;
}

return $summary;
