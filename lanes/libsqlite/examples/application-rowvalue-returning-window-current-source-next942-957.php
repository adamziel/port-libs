<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield957', option_value || ':yield957', bytes + 641) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt957', option_value || ':attempt957', bytes + 541) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry957', option_value || ':retry957', bytes + 651) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 942; $next <= 957; $next++) {
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
for ($next = 942; $next <= 957; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[942]['next942_handoff']['next941_ready'] === true);
assert($plans[945]['next945_ready'] === true);
assert($plans[949]['next949_ready'] === true);
assert($plans[953]['next953_ready'] === true);
assert($plans[957]['next957_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next942-957',
    'candidateStatuses' => array_values($statuses),
    'next942Handoff' => $plans[942]['next942_handoff']['next942_handoff'],
    'next942AfterReadyRange' => $plans[942]['next942_handoff']['after_ready_range'],
    'next942ConsumesNext941Ready' => $plans[942]['next942_handoff']['next941_ready'],
    'next943SourceAudit' => $plans[943]['next943_source_audit']['next943_source_audit'],
    'next943PreservesCurrentSource' => $plans[943]['next943_source_audit']['retry_rows_preserve_current_source'],
    'next944Preflight' => $plans[944]['next944_preflight']['next944_preflight'],
    'next944KeepsThroughputHigh' => $plans[944]['next944_preflight']['keeps_libsqlite_throughput_high'],
    'next945Final' => $plans[945]['next945_final']['next945_final'],
    'next945Ready' => $plans[945]['next945_ready'],
    'next946Handoff' => $plans[946]['next946_handoff']['next946_handoff'],
    'next946AfterReadyRange' => $plans[946]['next946_handoff']['after_ready_range'],
    'next949Ready' => $plans[949]['next949_ready'],
    'next953Ready' => $plans[953]['next953_ready'],
    'next957Final' => $plans[957]['next957_final']['next957_final'],
    'next957Ready' => $plans[957]['next957_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next942-957 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next941_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next942-957 self-test passed\n";
    return;
}

return $summary;
