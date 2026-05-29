<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield373', option_value || ':yield373', bytes + 97) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt373', option_value || ':attempt373', bytes + 59) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry373', option_value || ':retry373', bytes + 83) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($step = 366; $step <= 373; $step++) {
    $plans[$step] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceHandoffContinuationStep($step, ...$args);
}

$statuses = [
    $plans[366]['status'],
    $plans[367]['status'],
    $plans[368]['status'],
    $plans[369]['status'],
    $plans[370]['status'],
    $plans[371]['status'],
    $plans[372]['status'],
    $plans[373]['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next366',
    'rowvalue-update-delete-returning-window-current-source-next367',
    'rowvalue-update-delete-returning-window-current-source-next368',
    'rowvalue-update-delete-returning-window-current-source-next369',
    'rowvalue-update-delete-returning-window-current-source-next370',
    'rowvalue-update-delete-returning-window-current-source-next371',
    'rowvalue-update-delete-returning-window-current-source-next372',
    'rowvalue-update-delete-returning-window-current-source-next373',
]);
assert($plans[369]['next369_ready'] === true);
assert($plans[373]['next373_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next366-373',
    'candidateStatuses' => $statuses,
    'next366Handoff' => $plans[366]['next366_handoff']['next366_handoff'],
    'next366AfterReadyRange' => $plans[366]['next366_handoff']['after_ready_range'],
    'next367SourceAudit' => $plans[367]['next367_source_audit']['next367_source_audit'],
    'next367PreservesCurrentSource' => $plans[367]['next367_source_audit']['retry_rows_preserve_current_source'],
    'next368Preflight' => $plans[368]['next368_preflight']['next368_preflight'],
    'next368KeepsThroughputHigh' => $plans[368]['next368_preflight']['keeps_libsqlite_throughput_high'],
    'next369Final' => $plans[369]['next369_final']['next369_final'],
    'next369Ready' => $plans[369]['next369_ready'],
    'next370Handoff' => $plans[370]['next370_handoff']['next370_handoff'],
    'next370AfterReadyRange' => $plans[370]['next370_handoff']['after_ready_range'],
    'next371SourceAudit' => $plans[371]['next371_source_audit']['next371_source_audit'],
    'next371PreservesCurrentSource' => $plans[371]['next371_source_audit']['retry_rows_preserve_current_source'],
    'next372Preflight' => $plans[372]['next372_preflight']['next372_preflight'],
    'next372KeepsThroughputHigh' => $plans[372]['next372_preflight']['keeps_libsqlite_throughput_high'],
    'next373Final' => $plans[373]['next373_final']['next373_final'],
    'next373Ready' => $plans[373]['next373_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next366-373 row-value UPDATE/DELETE RETURNING window current-source continuation after the merged next358-365 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next366-373 self-test passed\n";
    return;
}

return $summary;
