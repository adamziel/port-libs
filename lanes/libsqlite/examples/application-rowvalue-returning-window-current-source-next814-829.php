<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield829', option_value || ':yield829', bytes + 529) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt829', option_value || ':attempt829', bytes + 429) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry829', option_value || ':retry829', bytes + 539) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 814; $next <= 829; $next++) {
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
for ($next = 814; $next <= 829; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[814]['next814_handoff']['next813_ready'] === true);
assert($plans[817]['next817_ready'] === true);
assert($plans[821]['next821_ready'] === true);
assert($plans[825]['next825_ready'] === true);
assert($plans[829]['next829_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next814-829',
    'candidateStatuses' => array_values($statuses),
    'next814Handoff' => $plans[814]['next814_handoff']['next814_handoff'],
    'next814AfterReadyRange' => $plans[814]['next814_handoff']['after_ready_range'],
    'next814ConsumesNext813Ready' => $plans[814]['next814_handoff']['next813_ready'],
    'next815SourceAudit' => $plans[815]['next815_source_audit']['next815_source_audit'],
    'next815PreservesCurrentSource' => $plans[815]['next815_source_audit']['retry_rows_preserve_current_source'],
    'next816Preflight' => $plans[816]['next816_preflight']['next816_preflight'],
    'next816KeepsThroughputHigh' => $plans[816]['next816_preflight']['keeps_libsqlite_throughput_high'],
    'next817Final' => $plans[817]['next817_final']['next817_final'],
    'next817Ready' => $plans[817]['next817_ready'],
    'next818Handoff' => $plans[818]['next818_handoff']['next818_handoff'],
    'next818AfterReadyRange' => $plans[818]['next818_handoff']['after_ready_range'],
    'next821Ready' => $plans[821]['next821_ready'],
    'next825Ready' => $plans[825]['next825_ready'],
    'next829Final' => $plans[829]['next829_final']['next829_final'],
    'next829Ready' => $plans[829]['next829_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next814-829 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next813_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next814-829 self-test passed\n";
    return;
}

return $summary;
