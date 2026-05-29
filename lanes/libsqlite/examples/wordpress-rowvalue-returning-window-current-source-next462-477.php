<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield477', option_value || ':yield477', bytes + 177) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt477', option_value || ':attempt477', bytes + 113) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry477', option_value || ':retry477', bytes + 175) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 462; $next <= 477; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 462; $next <= 477; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[465]['next465_ready'] === true);
assert($plans[469]['next469_ready'] === true);
assert($plans[473]['next473_ready'] === true);
assert($plans[477]['next477_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next462-477',
    'candidateStatuses' => array_values($statuses),
    'next462Handoff' => $plans[462]['next462_handoff']['next462_handoff'],
    'next462AfterReadyRange' => $plans[462]['next462_handoff']['after_ready_range'],
    'next463SourceAudit' => $plans[463]['next463_source_audit']['next463_source_audit'],
    'next463PreservesCurrentSource' => $plans[463]['next463_source_audit']['retry_rows_preserve_current_source'],
    'next464Preflight' => $plans[464]['next464_preflight']['next464_preflight'],
    'next464KeepsThroughputHigh' => $plans[464]['next464_preflight']['keeps_libsqlite_throughput_high'],
    'next465Final' => $plans[465]['next465_final']['next465_final'],
    'next465Ready' => $plans[465]['next465_ready'],
    'next466Handoff' => $plans[466]['next466_handoff']['next466_handoff'],
    'next466AfterReadyRange' => $plans[466]['next466_handoff']['after_ready_range'],
    'next469Ready' => $plans[469]['next469_ready'],
    'next473Ready' => $plans[473]['next473_ready'],
    'next477Final' => $plans[477]['next477_final']['next477_final'],
    'next477Ready' => $plans[477]['next477_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next462-477 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next446-461 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next462-477 self-test passed\n";
    return;
}

return $summary;
