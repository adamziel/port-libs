<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield365', option_value || ':yield365', bytes + 89) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt365', option_value || ':attempt365', bytes + 53) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry365', option_value || ':retry365', bytes + 79) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next358 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext358(...$args);
$next359 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext359(...$args);
$next360 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext360(...$args);
$next361 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext361(...$args);
$next362 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext362(...$args);
$next363 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext363(...$args);
$next364 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext364(...$args);
$next365 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext365(...$args);

$statuses = [
    $next358['status'],
    $next359['status'],
    $next360['status'],
    $next361['status'],
    $next362['status'],
    $next363['status'],
    $next364['status'],
    $next365['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next358',
    'rowvalue-update-delete-returning-window-current-source-next359',
    'rowvalue-update-delete-returning-window-current-source-next360',
    'rowvalue-update-delete-returning-window-current-source-next361',
    'rowvalue-update-delete-returning-window-current-source-next362',
    'rowvalue-update-delete-returning-window-current-source-next363',
    'rowvalue-update-delete-returning-window-current-source-next364',
    'rowvalue-update-delete-returning-window-current-source-next365',
]);
assert($next361['next361_ready'] === true);
assert($next365['next365_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next358-365',
    'candidateStatuses' => $statuses,
    'next358Handoff' => $next358['next358_handoff']['next358_handoff'],
    'next358AfterReadyRange' => $next358['next358_handoff']['after_ready_range'],
    'next359SourceAudit' => $next359['next359_source_audit']['next359_source_audit'],
    'next359PreservesCurrentSource' => $next359['next359_source_audit']['retry_rows_preserve_current_source'],
    'next360Preflight' => $next360['next360_preflight']['next360_preflight'],
    'next360KeepsThroughputHigh' => $next360['next360_preflight']['keeps_libsqlite_throughput_high'],
    'next361Final' => $next361['next361_final']['next361_final'],
    'next361Ready' => $next361['next361_ready'],
    'next362Handoff' => $next362['next362_handoff']['next362_handoff'],
    'next362AfterReadyRange' => $next362['next362_handoff']['after_ready_range'],
    'next363SourceAudit' => $next363['next363_source_audit']['next363_source_audit'],
    'next363PreservesCurrentSource' => $next363['next363_source_audit']['retry_rows_preserve_current_source'],
    'next364Preflight' => $next364['next364_preflight']['next364_preflight'],
    'next364KeepsThroughputHigh' => $next364['next364_preflight']['keeps_libsqlite_throughput_high'],
    'next365Final' => $next365['next365_final']['next365_final'],
    'next365Ready' => $next365['next365_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next358-365 row-value UPDATE/DELETE RETURNING window current-source continuation after the merged next350-357 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next358-365 self-test passed\n";
    return;
}

return $summary;
