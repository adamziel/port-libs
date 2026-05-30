<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1165', option_value || ':yield1165', bytes + 859) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1165', option_value || ':attempt1165', bytes + 739) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1165', option_value || ':retry1165', bytes + 863) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1150; $next <= 1165; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1150; $next <= 1165; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1150]['next1150_handoff']['next1149_ready'] === true);
assert($plans[1153]['next1153_ready'] === true);
assert($plans[1157]['next1157_ready'] === true);
assert($plans[1161]['next1161_ready'] === true);
assert($plans[1165]['next1165_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-ready-publication-final-handoff',
    'candidateStatuses' => array_values($statuses),
    'initialHandoffToken' => $plans[1150]['next1150_handoff']['next1150_handoff'],
    'initialAfterReadyRange' => $plans[1150]['next1150_handoff']['after_ready_range'],
    'initialConsumesPriorReady' => $plans[1150]['next1150_handoff']['next1149_ready'],
    'initialSourceAuditToken' => $plans[1151]['next1151_source_audit']['next1151_source_audit'],
    'initialPreservesCurrentSource' => $plans[1151]['next1151_source_audit']['retry_rows_preserve_current_source'],
    'initialPreflightToken' => $plans[1152]['next1152_preflight']['next1152_preflight'],
    'initialKeepsThroughputHigh' => $plans[1152]['next1152_preflight']['keeps_libsqlite_throughput_high'],
    'initialFinalToken' => $plans[1153]['next1153_final']['next1153_final'],
    'initialReady' => $plans[1153]['next1153_ready'],
    'secondHandoffToken' => $plans[1154]['next1154_handoff']['next1154_handoff'],
    'secondAfterReadyRange' => $plans[1154]['next1154_handoff']['after_ready_range'],
    'secondSealReady' => $plans[1157]['next1157_ready'],
    'thirdSealReady' => $plans[1161]['next1161_ready'],
    'finalSealToken' => $plans[1165]['next1165_final']['next1165_final'],
    'finalSealReady' => $plans[1165]['next1165_ready'],
    'applicationUse' => 'Copied wp_options imports validate the final row-value UPDATE/DELETE RETURNING window current-source continuation handoff from the prior ready seal.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-ready-publication-final-handoff self-test passed\n";
    return;
}

return $summary;
