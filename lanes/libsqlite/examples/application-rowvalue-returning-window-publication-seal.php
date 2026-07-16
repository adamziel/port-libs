<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield525', option_value || ':yield525', bytes + 225) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt525', option_value || ':attempt525', bytes + 161) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry525', option_value || ':retry525', bytes + 223) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 510; $next <= 525; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePublicationSealStep($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 510; $next <= 525; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[513]['next513_ready'] === true);
assert($plans[517]['next517_ready'] === true);
assert($plans[521]['next521_ready'] === true);
assert($plans[525]['next525_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next510-525',
    'candidateStatuses' => array_values($statuses),
    'next510Handoff' => $plans[510]['next510_handoff']['next510_handoff'],
    'next510AfterReadyRange' => $plans[510]['next510_handoff']['after_ready_range'],
    'next511SourceAudit' => $plans[511]['next511_source_audit']['next511_source_audit'],
    'next511PreservesCurrentSource' => $plans[511]['next511_source_audit']['retry_rows_preserve_current_source'],
    'next512Preflight' => $plans[512]['next512_preflight']['next512_preflight'],
    'next512KeepsThroughputHigh' => $plans[512]['next512_preflight']['keeps_libsqlite_throughput_high'],
    'next513Final' => $plans[513]['next513_final']['next513_final'],
    'next513Ready' => $plans[513]['next513_ready'],
    'next514Handoff' => $plans[514]['next514_handoff']['next514_handoff'],
    'next514AfterReadyRange' => $plans[514]['next514_handoff']['after_ready_range'],
    'next517Ready' => $plans[517]['next517_ready'],
    'next521Ready' => $plans[521]['next521_ready'],
    'next525Final' => $plans[525]['next525_final']['next525_final'],
    'next525Ready' => $plans[525]['next525_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next510-525 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next494-509 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-publication-seal self-test passed\n";
    return;
}

return $summary;
