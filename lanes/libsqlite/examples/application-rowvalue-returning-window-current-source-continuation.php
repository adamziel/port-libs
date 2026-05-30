<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
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
for ($step = 446; $step <= 461; $step++) {
    $plans[$step] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowCurrentSourceContinuation($step, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($step = 446; $step <= 461; $step++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $step;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[449]['next449_ready'] === true);
assert($plans[453]['next453_ready'] === true);
assert($plans[457]['next457_ready'] === true);
assert($plans[461]['next461_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-continuation',
    'candidateStatuses' => array_values($statuses),
    'handoffDigest' => $plans[446]['next446_handoff']['next446_handoff'],
    'handoffAfterReadyRange' => $plans[446]['next446_handoff']['after_ready_range'],
    'sourceAuditDigest' => $plans[447]['next447_source_audit']['next447_source_audit'],
    'preservesCurrentSource' => $plans[447]['next447_source_audit']['retry_rows_preserve_current_source'],
    'preflightDigest' => $plans[448]['next448_preflight']['next448_preflight'],
    'keepsThroughputHigh' => $plans[448]['next448_preflight']['keeps_libsqlite_throughput_high'],
    'firstReadyFinalDigest' => $plans[449]['next449_final']['next449_final'],
    'firstReady' => $plans[449]['next449_ready'],
    'secondHandoffDigest' => $plans[450]['next450_handoff']['next450_handoff'],
    'secondHandoffAfterReadyRange' => $plans[450]['next450_handoff']['after_ready_range'],
    'midReady' => $plans[453]['next453_ready'],
    'lateReady' => $plans[457]['next457_ready'],
    'finalDigest' => $plans[461]['next461_final']['next461_final'],
    'finalReady' => $plans[461]['next461_ready'],
    'applicationUse' => 'Copied wp_options imports validate the row-value UPDATE/DELETE RETURNING window current-source continuation after the merged publication seal while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-continuation self-test passed\n";
    return;
}

return $summary;
