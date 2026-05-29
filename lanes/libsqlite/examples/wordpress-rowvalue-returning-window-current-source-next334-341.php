<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield341', option_value || ':yield341', bytes + 71) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt341', option_value || ':attempt341', bytes + 37) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry341', option_value || ':retry341', bytes + 61) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next334 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext334(...$args);
$next335 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext335(...$args);
$next336 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext336(...$args);
$next337 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext337(...$args);
$next338 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext338(...$args);
$next339 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext339(...$args);
$next340 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext340(...$args);
$next341 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext341(...$args);

$statuses = [
    $next334['status'],
    $next335['status'],
    $next336['status'],
    $next337['status'],
    $next338['status'],
    $next339['status'],
    $next340['status'],
    $next341['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next334',
    'rowvalue-update-delete-returning-window-current-source-next335',
    'rowvalue-update-delete-returning-window-current-source-next336',
    'rowvalue-update-delete-returning-window-current-source-next337',
    'rowvalue-update-delete-returning-window-current-source-next338',
    'rowvalue-update-delete-returning-window-current-source-next339',
    'rowvalue-update-delete-returning-window-current-source-next340',
    'rowvalue-update-delete-returning-window-current-source-next341',
]);
assert($next337['next337_ready'] === true);
assert($next341['next341_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next334-341',
    'candidateStatuses' => $statuses,
    'next334Handoff' => $next334['next334_handoff']['next334_handoff'],
    'next334AfterReadyRange' => $next334['next334_handoff']['after_ready_range'],
    'next335SourceAudit' => $next335['next335_source_audit']['next335_source_audit'],
    'next335PreservesCurrentSource' => $next335['next335_source_audit']['retry_rows_preserve_current_source'],
    'next336Preflight' => $next336['next336_preflight']['next336_preflight'],
    'next336KeepsThroughputHigh' => $next336['next336_preflight']['keeps_libsqlite_throughput_high'],
    'next337Final' => $next337['next337_final']['next337_final'],
    'next337Ready' => $next337['next337_ready'],
    'next338Handoff' => $next338['next338_handoff']['next338_handoff'],
    'next338AfterReadyRange' => $next338['next338_handoff']['after_ready_range'],
    'next339SourceAudit' => $next339['next339_source_audit']['next339_source_audit'],
    'next339PreservesCurrentSource' => $next339['next339_source_audit']['retry_rows_preserve_current_source'],
    'next340Preflight' => $next340['next340_preflight']['next340_preflight'],
    'next340KeepsThroughputHigh' => $next340['next340_preflight']['keeps_libsqlite_throughput_high'],
    'next341Final' => $next341['next341_final']['next341_final'],
    'next341Ready' => $next341['next341_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next334-341 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next326-333 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next334-341 self-test passed\n";
    return;
}

return $summary;
