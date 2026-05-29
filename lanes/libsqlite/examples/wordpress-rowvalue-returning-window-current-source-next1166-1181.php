<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1181', option_value || ':yield1181', bytes + 859) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1181', option_value || ':attempt1181', bytes + 739) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1181', option_value || ':retry1181', bytes + 863) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationRange(1166, 1181, ...$args);

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1166; $next <= 1181; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1166]['next1166_handoff']['next1165_ready'] === true);
assert($plans[1169]['next1169_ready'] === true);
assert($plans[1173]['next1173_ready'] === true);
assert($plans[1177]['next1177_ready'] === true);
assert($plans[1181]['next1181_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1166-1181',
    'candidateStatuses' => array_values($statuses),
    'next1166Handoff' => $plans[1166]['next1166_handoff']['next1166_handoff'],
    'next1166AfterReadyRange' => $plans[1166]['next1166_handoff']['after_ready_range'],
    'next1166ConsumesNext1165Ready' => $plans[1166]['next1166_handoff']['next1165_ready'],
    'next1167SourceAudit' => $plans[1167]['next1167_source_audit']['next1167_source_audit'],
    'next1167PreservesCurrentSource' => $plans[1167]['next1167_source_audit']['retry_rows_preserve_current_source'],
    'next1168Preflight' => $plans[1168]['next1168_preflight']['next1168_preflight'],
    'next1168KeepsThroughputHigh' => $plans[1168]['next1168_preflight']['keeps_libsqlite_throughput_high'],
    'next1169Final' => $plans[1169]['next1169_final']['next1169_final'],
    'next1169Ready' => $plans[1169]['next1169_ready'],
    'next1170Handoff' => $plans[1170]['next1170_handoff']['next1170_handoff'],
    'next1170AfterReadyRange' => $plans[1170]['next1170_handoff']['after_ready_range'],
    'next1173Ready' => $plans[1173]['next1173_ready'],
    'next1177Ready' => $plans[1177]['next1177_ready'],
    'next1181Final' => $plans[1181]['next1181_final']['next1181_final'],
    'next1181Ready' => $plans[1181]['next1181_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1166-1181 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1165_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1166-1181 self-test passed\n";
    return;
}

return $summary;
