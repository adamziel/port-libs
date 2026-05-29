<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield445', option_value || ':yield445', bytes + 145) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt445', option_value || ':attempt445', bytes + 91) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry445', option_value || ':retry445', bytes + 143) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 430; $next <= 445; $next++) {
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
for ($next = 430; $next <= 445; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[433]['next433_ready'] === true);
assert($plans[437]['next437_ready'] === true);
assert($plans[441]['next441_ready'] === true);
assert($plans[445]['next445_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next430-445',
    'candidateStatuses' => array_values($statuses),
    'next430Handoff' => $plans[430]['next430_handoff']['next430_handoff'],
    'next430AfterReadyRange' => $plans[430]['next430_handoff']['after_ready_range'],
    'next431SourceAudit' => $plans[431]['next431_source_audit']['next431_source_audit'],
    'next431PreservesCurrentSource' => $plans[431]['next431_source_audit']['retry_rows_preserve_current_source'],
    'next432Preflight' => $plans[432]['next432_preflight']['next432_preflight'],
    'next432KeepsThroughputHigh' => $plans[432]['next432_preflight']['keeps_libsqlite_throughput_high'],
    'next433Final' => $plans[433]['next433_final']['next433_final'],
    'next433Ready' => $plans[433]['next433_ready'],
    'next434Handoff' => $plans[434]['next434_handoff']['next434_handoff'],
    'next434AfterReadyRange' => $plans[434]['next434_handoff']['after_ready_range'],
    'next437Ready' => $plans[437]['next437_ready'],
    'next441Ready' => $plans[441]['next441_ready'],
    'next445Final' => $plans[445]['next445_final']['next445_final'],
    'next445Ready' => $plans[445]['next445_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next430-445 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next414-429 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next430-445 self-test passed\n";
    return;
}

return $summary;
