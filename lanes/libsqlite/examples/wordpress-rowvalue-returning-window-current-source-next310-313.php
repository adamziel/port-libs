<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield313', option_value || ':yield313', bytes + 53) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt313', option_value || ':attempt313', bytes + 19) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry313', option_value || ':retry313', bytes + 43) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next310 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext310(...$args);
$next311 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext311(...$args);
$next312 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext312(...$args);
$next313 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext313(...$args);

$statuses = [$next310['status'], $next311['status'], $next312['status'], $next313['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next310',
    'rowvalue-update-delete-returning-window-current-source-next311',
    'rowvalue-update-delete-returning-window-current-source-next312',
    'rowvalue-update-delete-returning-window-current-source-next313',
]);
assert($next313['next313_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next310-313',
    'candidateStatuses' => $statuses,
    'next310Handoff' => $next310['next310_handoff']['next310_handoff'],
    'next310AfterReadyRange' => $next310['next310_handoff']['after_ready_range'],
    'next311SourceAudit' => $next311['next311_source_audit']['next311_source_audit'],
    'next311PreservesCurrentSource' => $next311['next311_source_audit']['retry_rows_preserve_current_source'],
    'next312Preflight' => $next312['next312_preflight']['next312_preflight'],
    'next312KeepsThroughputHigh' => $next312['next312_preflight']['keeps_libsqlite_throughput_high'],
    'next313Final' => $next313['next313_final']['next313_final'],
    'next313Ready' => $next313['next313_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next310-313 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next306-309 handoff while preserving independent libsqlite preflight throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next310-313 self-test passed\n";
    return;
}

return $summary;
