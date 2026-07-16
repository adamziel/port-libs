<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield333', option_value || ':yield333', bytes + 71) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt333', option_value || ':attempt333', bytes + 37) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry333', option_value || ':retry333', bytes + 61) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next326 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(326, ...$args);
$next327 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(327, ...$args);
$next328 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(328, ...$args);
$next329 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(329, ...$args);
$next330 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(330, ...$args);
$next331 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(331, ...$args);
$next332 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(332, ...$args);
$next333 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePrePublicationStep(333, ...$args);

$statuses = [
    $next326['status'],
    $next327['status'],
    $next328['status'],
    $next329['status'],
    $next330['status'],
    $next331['status'],
    $next332['status'],
    $next333['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next326',
    'rowvalue-update-delete-returning-window-current-source-next327',
    'rowvalue-update-delete-returning-window-current-source-next328',
    'rowvalue-update-delete-returning-window-current-source-next329',
    'rowvalue-update-delete-returning-window-current-source-next330',
    'rowvalue-update-delete-returning-window-current-source-next331',
    'rowvalue-update-delete-returning-window-current-source-next332',
    'rowvalue-update-delete-returning-window-current-source-next333',
]);
assert($next329['next329_ready'] === true);
assert($next333['next333_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next326-333',
    'candidateStatuses' => $statuses,
    'next326Handoff' => $next326['next326_handoff']['next326_handoff'],
    'next326AfterReadyRange' => $next326['next326_handoff']['after_ready_range'],
    'next327SourceAudit' => $next327['next327_source_audit']['next327_source_audit'],
    'next327PreservesCurrentSource' => $next327['next327_source_audit']['retry_rows_preserve_current_source'],
    'next328Preflight' => $next328['next328_preflight']['next328_preflight'],
    'next328KeepsThroughputHigh' => $next328['next328_preflight']['keeps_libsqlite_throughput_high'],
    'next329Final' => $next329['next329_final']['next329_final'],
    'next329Ready' => $next329['next329_ready'],
    'next330Handoff' => $next330['next330_handoff']['next330_handoff'],
    'next330AfterReadyRange' => $next330['next330_handoff']['after_ready_range'],
    'next331SourceAudit' => $next331['next331_source_audit']['next331_source_audit'],
    'next331PreservesCurrentSource' => $next331['next331_source_audit']['retry_rows_preserve_current_source'],
    'next332Preflight' => $next332['next332_preflight']['next332_preflight'],
    'next332KeepsThroughputHigh' => $next332['next332_preflight']['keeps_libsqlite_throughput_high'],
    'next333Final' => $next333['next333_final']['next333_final'],
    'next333Ready' => $next333['next333_ready'],
    'applicationUse' => 'Copied wp_options imports can validate the next326-333 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next322-325 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next326-333 self-test passed\n";
    return;
}

return $summary;
