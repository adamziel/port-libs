<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield653', option_value || ':yield653', bytes + 353) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt653', option_value || ':attempt653', bytes + 253) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry653', option_value || ':retry653', bytes + 363) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 638; $next <= 653; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuationThroughStep($args[0], $args[1], $args[2], $args[3], $args[4], $next);
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
for ($next = 638; $next <= 653; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[641]['next641_ready'] === true);
assert($plans[645]['next645_ready'] === true);
assert($plans[649]['next649_ready'] === true);
assert($plans[653]['next653_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next638-653',
    'candidateStatuses' => array_values($statuses),
    'next638Handoff' => $plans[638]['next638_handoff']['next638_handoff'],
    'next638AfterReadyRange' => $plans[638]['next638_handoff']['after_ready_range'],
    'next639SourceAudit' => $plans[639]['next639_source_audit']['next639_source_audit'],
    'next639PreservesCurrentSource' => $plans[639]['next639_source_audit']['retry_rows_preserve_current_source'],
    'next640Preflight' => $plans[640]['next640_preflight']['next640_preflight'],
    'next640KeepsThroughputHigh' => $plans[640]['next640_preflight']['keeps_libsqlite_throughput_high'],
    'next641Final' => $plans[641]['next641_final']['next641_final'],
    'next641Ready' => $plans[641]['next641_ready'],
    'next642Handoff' => $plans[642]['next642_handoff']['next642_handoff'],
    'next642AfterReadyRange' => $plans[642]['next642_handoff']['after_ready_range'],
    'next645Ready' => $plans[645]['next645_ready'],
    'next649Ready' => $plans[649]['next649_ready'],
    'next653Final' => $plans[653]['next653_final']['next653_final'],
    'next653Ready' => $plans[653]['next653_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next638-653 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next622-637 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next638-653 self-test passed\n";
    return;
}

return $summary;
