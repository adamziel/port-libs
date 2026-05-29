<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield461', option_value || ':yield461', bytes + 161) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt461', option_value || ':attempt461', bytes + 97) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry461', option_value || ':retry461', bytes + 159) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 446; $next <= 461; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 446; $next <= 461; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[449]['next449_ready'] === true);
assert($plans[453]['next453_ready'] === true);
assert($plans[457]['next457_ready'] === true);
assert($plans[461]['next461_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next446-461',
    'candidateStatuses' => array_values($statuses),
    'next446Handoff' => $plans[446]['next446_handoff']['next446_handoff'],
    'next446AfterReadyRange' => $plans[446]['next446_handoff']['after_ready_range'],
    'next447SourceAudit' => $plans[447]['next447_source_audit']['next447_source_audit'],
    'next447PreservesCurrentSource' => $plans[447]['next447_source_audit']['retry_rows_preserve_current_source'],
    'next448Preflight' => $plans[448]['next448_preflight']['next448_preflight'],
    'next448KeepsThroughputHigh' => $plans[448]['next448_preflight']['keeps_libsqlite_throughput_high'],
    'next449Final' => $plans[449]['next449_final']['next449_final'],
    'next449Ready' => $plans[449]['next449_ready'],
    'next450Handoff' => $plans[450]['next450_handoff']['next450_handoff'],
    'next450AfterReadyRange' => $plans[450]['next450_handoff']['after_ready_range'],
    'next453Ready' => $plans[453]['next453_ready'],
    'next457Ready' => $plans[457]['next457_ready'],
    'next461Final' => $plans[461]['next461_final']['next461_final'],
    'next461Ready' => $plans[461]['next461_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next446-461 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next430-445 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next446-461 self-test passed\n";
    return;
}

return $summary;
