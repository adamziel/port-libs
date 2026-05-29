<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield413', option_value || ':yield413', bytes + 113) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt413', option_value || ':attempt413', bytes + 71) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry413', option_value || ':retry413', bytes + 107) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 398; $next <= 413; $next++) {
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
for ($next = 398; $next <= 413; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[401]['next401_ready'] === true);
assert($plans[405]['next405_ready'] === true);
assert($plans[409]['next409_ready'] === true);
assert($plans[413]['next413_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next398-413',
    'candidateStatuses' => array_values($statuses),
    'next398Handoff' => $plans[398]['next398_handoff']['next398_handoff'],
    'next398AfterReadyRange' => $plans[398]['next398_handoff']['after_ready_range'],
    'next399SourceAudit' => $plans[399]['next399_source_audit']['next399_source_audit'],
    'next399PreservesCurrentSource' => $plans[399]['next399_source_audit']['retry_rows_preserve_current_source'],
    'next400Preflight' => $plans[400]['next400_preflight']['next400_preflight'],
    'next400KeepsThroughputHigh' => $plans[400]['next400_preflight']['keeps_libsqlite_throughput_high'],
    'next401Final' => $plans[401]['next401_final']['next401_final'],
    'next401Ready' => $plans[401]['next401_ready'],
    'next402Handoff' => $plans[402]['next402_handoff']['next402_handoff'],
    'next402AfterReadyRange' => $plans[402]['next402_handoff']['after_ready_range'],
    'next405Ready' => $plans[405]['next405_ready'],
    'next409Ready' => $plans[409]['next409_ready'],
    'next413Final' => $plans[413]['next413_final']['next413_final'],
    'next413Ready' => $plans[413]['next413_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next398-413 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next382-397 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next398-413 self-test passed\n";
    return;
}

return $summary;
