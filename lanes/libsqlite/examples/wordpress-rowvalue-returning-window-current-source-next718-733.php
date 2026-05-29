<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield733', option_value || ':yield733', bytes + 433) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt733', option_value || ':attempt733', bytes + 333) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry733', option_value || ':retry733', bytes + 443) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 718; $next <= 733; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
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
for ($next = 718; $next <= 733; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[718]['next718_handoff']['next717_ready'] === true);
assert($plans[721]['next721_ready'] === true);
assert($plans[725]['next725_ready'] === true);
assert($plans[729]['next729_ready'] === true);
assert($plans[733]['next733_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next718-733',
    'candidateStatuses' => array_values($statuses),
    'next718Handoff' => $plans[718]['next718_handoff']['next718_handoff'],
    'next718AfterReadyRange' => $plans[718]['next718_handoff']['after_ready_range'],
    'next718ConsumesNext717Ready' => $plans[718]['next718_handoff']['next717_ready'],
    'next719SourceAudit' => $plans[719]['next719_source_audit']['next719_source_audit'],
    'next719PreservesCurrentSource' => $plans[719]['next719_source_audit']['retry_rows_preserve_current_source'],
    'next720Preflight' => $plans[720]['next720_preflight']['next720_preflight'],
    'next720KeepsThroughputHigh' => $plans[720]['next720_preflight']['keeps_libsqlite_throughput_high'],
    'next721Final' => $plans[721]['next721_final']['next721_final'],
    'next721Ready' => $plans[721]['next721_ready'],
    'next722Handoff' => $plans[722]['next722_handoff']['next722_handoff'],
    'next722AfterReadyRange' => $plans[722]['next722_handoff']['after_ready_range'],
    'next725Ready' => $plans[725]['next725_ready'],
    'next729Ready' => $plans[729]['next729_ready'],
    'next733Final' => $plans[733]['next733_final']['next733_final'],
    'next733Ready' => $plans[733]['next733_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next718-733 row-value UPDATE/DELETE RETURNING window current-source continuation after integrated next702-717 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next718-733 self-test passed\n";
    return;
}

return $summary;
