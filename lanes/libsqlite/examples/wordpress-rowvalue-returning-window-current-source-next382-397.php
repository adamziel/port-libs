<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield397', option_value || ':yield397', bytes + 103) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt397', option_value || ':attempt397', bytes + 67) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry397', option_value || ':retry397', bytes + 97) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 382; $next <= 397; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceFollowOnStep($next, ...$args);
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
for ($next = 382; $next <= 397; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[385]['next385_ready'] === true);
assert($plans[389]['next389_ready'] === true);
assert($plans[393]['next393_ready'] === true);
assert($plans[397]['next397_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next382-397',
    'candidateStatuses' => array_values($statuses),
    'next382Handoff' => $plans[382]['next382_handoff']['next382_handoff'],
    'next382AfterReadyRange' => $plans[382]['next382_handoff']['after_ready_range'],
    'next383SourceAudit' => $plans[383]['next383_source_audit']['next383_source_audit'],
    'next383PreservesCurrentSource' => $plans[383]['next383_source_audit']['retry_rows_preserve_current_source'],
    'next384Preflight' => $plans[384]['next384_preflight']['next384_preflight'],
    'next384KeepsThroughputHigh' => $plans[384]['next384_preflight']['keeps_libsqlite_throughput_high'],
    'next385Final' => $plans[385]['next385_final']['next385_final'],
    'next385Ready' => $plans[385]['next385_ready'],
    'next386Handoff' => $plans[386]['next386_handoff']['next386_handoff'],
    'next386AfterReadyRange' => $plans[386]['next386_handoff']['after_ready_range'],
    'next389Ready' => $plans[389]['next389_ready'],
    'next393Ready' => $plans[393]['next393_ready'],
    'next397Final' => $plans[397]['next397_final']['next397_final'],
    'next397Ready' => $plans[397]['next397_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next382-397 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next374-381 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next382-397 self-test passed\n";
    return;
}

return $summary;
