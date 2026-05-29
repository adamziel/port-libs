<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield557', option_value || ':yield557', bytes + 257) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt557', option_value || ':attempt557', bytes + 193) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry557', option_value || ':retry557', bytes + 251) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 542; $next <= 557; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuationThroughStep(
        $args[0],
        $args[1],
        $args[2],
        $args[3],
        $args[4],
        $next
    );
    $plans[$next] = array_intersect_key($plan, array_flip([
        'status',
        'next' . $next . '_handoff',
        'next' . $next . '_source_audit',
        'next' . $next . '_preflight',
        'next' . $next . '_final',
        'next' . $next . '_ready',
    ]));
    unset($plan);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 542; $next <= 557; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[545]['next545_ready'] === true);
assert($plans[549]['next549_ready'] === true);
assert($plans[553]['next553_ready'] === true);
assert($plans[557]['next557_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next542-557',
    'candidateStatuses' => array_values($statuses),
    'next542Handoff' => $plans[542]['next542_handoff']['next542_handoff'],
    'next542AfterReadyRange' => $plans[542]['next542_handoff']['after_ready_range'],
    'next543SourceAudit' => $plans[543]['next543_source_audit']['next543_source_audit'],
    'next543PreservesCurrentSource' => $plans[543]['next543_source_audit']['retry_rows_preserve_current_source'],
    'next544Preflight' => $plans[544]['next544_preflight']['next544_preflight'],
    'next544KeepsThroughputHigh' => $plans[544]['next544_preflight']['keeps_libsqlite_throughput_high'],
    'next545Final' => $plans[545]['next545_final']['next545_final'],
    'next545Ready' => $plans[545]['next545_ready'],
    'next546Handoff' => $plans[546]['next546_handoff']['next546_handoff'],
    'next546AfterReadyRange' => $plans[546]['next546_handoff']['after_ready_range'],
    'next549Ready' => $plans[549]['next549_ready'],
    'next553Ready' => $plans[553]['next553_ready'],
    'next557Final' => $plans[557]['next557_final']['next557_final'],
    'next557Ready' => $plans[557]['next557_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next542-557 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next526-541 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next542-557 self-test passed\n";
    return;
}

return $summary;
