<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield573', option_value || ':yield573', bytes + 263) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt573', option_value || ':attempt573', bytes + 197) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry573', option_value || ':retry573', bytes + 269) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 558; $next <= 573; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuationThroughStep(
        $args[0],
        $args[1],
        $args[2],
        $args[3],
        $args[4],
        $next
    );
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 558; $next <= 573; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[561]['next561_ready'] === true);
assert($plans[565]['next565_ready'] === true);
assert($plans[569]['next569_ready'] === true);
assert($plans[573]['next573_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next558-573',
    'candidateStatuses' => array_values($statuses),
    'next558Handoff' => $plans[558]['next558_handoff']['next558_handoff'],
    'next558AfterReadyRange' => $plans[558]['next558_handoff']['after_ready_range'],
    'next559SourceAudit' => $plans[559]['next559_source_audit']['next559_source_audit'],
    'next559PreservesCurrentSource' => $plans[559]['next559_source_audit']['retry_rows_preserve_current_source'],
    'next560Preflight' => $plans[560]['next560_preflight']['next560_preflight'],
    'next560KeepsThroughputHigh' => $plans[560]['next560_preflight']['keeps_libsqlite_throughput_high'],
    'next561Final' => $plans[561]['next561_final']['next561_final'],
    'next561Ready' => $plans[561]['next561_ready'],
    'next562Handoff' => $plans[562]['next562_handoff']['next562_handoff'],
    'next562AfterReadyRange' => $plans[562]['next562_handoff']['after_ready_range'],
    'next565Ready' => $plans[565]['next565_ready'],
    'next569Ready' => $plans[569]['next569_ready'],
    'next573Final' => $plans[573]['next573_final']['next573_final'],
    'next573Ready' => $plans[573]['next573_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next558-573 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next542-557 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next558-573 self-test passed\n";
    return;
}

return $summary;
