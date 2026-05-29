<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield909', option_value || ':yield909', bytes + 593) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt909', option_value || ':attempt909', bytes + 493) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry909', option_value || ':retry909', bytes + 603) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 894; $next <= 909; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 894; $next <= 909; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[894]['next894_handoff']['next893_ready'] === true);
assert($plans[897]['next897_ready'] === true);
assert($plans[901]['next901_ready'] === true);
assert($plans[905]['next905_ready'] === true);
assert($plans[909]['next909_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next894-909',
    'candidateStatuses' => array_values($statuses),
    'next894Handoff' => $plans[894]['next894_handoff']['next894_handoff'],
    'next894AfterReadyRange' => $plans[894]['next894_handoff']['after_ready_range'],
    'next894ConsumesNext893Ready' => $plans[894]['next894_handoff']['next893_ready'],
    'next895SourceAudit' => $plans[895]['next895_source_audit']['next895_source_audit'],
    'next895PreservesCurrentSource' => $plans[895]['next895_source_audit']['retry_rows_preserve_current_source'],
    'next896Preflight' => $plans[896]['next896_preflight']['next896_preflight'],
    'next896KeepsThroughputHigh' => $plans[896]['next896_preflight']['keeps_libsqlite_throughput_high'],
    'next897Final' => $plans[897]['next897_final']['next897_final'],
    'next897Ready' => $plans[897]['next897_ready'],
    'next898Handoff' => $plans[898]['next898_handoff']['next898_handoff'],
    'next898AfterReadyRange' => $plans[898]['next898_handoff']['after_ready_range'],
    'next901Ready' => $plans[901]['next901_ready'],
    'next905Ready' => $plans[905]['next905_ready'],
    'next909Final' => $plans[909]['next909_final']['next909_final'],
    'next909Ready' => $plans[909]['next909_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next894-909 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next893_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next894-909 self-test passed\n";
    return;
}

return $summary;
