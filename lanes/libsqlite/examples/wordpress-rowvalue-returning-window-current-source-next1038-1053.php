<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1053', option_value || ':yield1053', bytes + 739) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1053', option_value || ':attempt1053', bytes + 631) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1053', option_value || ':retry1053', bytes + 743) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1038; $next <= 1053; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1038; $next <= 1053; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1038]['next1038_handoff']['next1037_ready'] === true);
assert($plans[1041]['next1041_ready'] === true);
assert($plans[1045]['next1045_ready'] === true);
assert($plans[1049]['next1049_ready'] === true);
assert($plans[1053]['next1053_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1038-1053',
    'candidateStatuses' => array_values($statuses),
    'next1038Handoff' => $plans[1038]['next1038_handoff']['next1038_handoff'],
    'next1038AfterReadyRange' => $plans[1038]['next1038_handoff']['after_ready_range'],
    'next1038ConsumesNext1037Ready' => $plans[1038]['next1038_handoff']['next1037_ready'],
    'next1039SourceAudit' => $plans[1039]['next1039_source_audit']['next1039_source_audit'],
    'next1039PreservesCurrentSource' => $plans[1039]['next1039_source_audit']['retry_rows_preserve_current_source'],
    'next1040Preflight' => $plans[1040]['next1040_preflight']['next1040_preflight'],
    'next1040KeepsThroughputHigh' => $plans[1040]['next1040_preflight']['keeps_libsqlite_throughput_high'],
    'next1041Final' => $plans[1041]['next1041_final']['next1041_final'],
    'next1041Ready' => $plans[1041]['next1041_ready'],
    'next1042Handoff' => $plans[1042]['next1042_handoff']['next1042_handoff'],
    'next1042AfterReadyRange' => $plans[1042]['next1042_handoff']['after_ready_range'],
    'next1045Ready' => $plans[1045]['next1045_ready'],
    'next1049Ready' => $plans[1049]['next1049_ready'],
    'next1053Final' => $plans[1053]['next1053_final']['next1053_final'],
    'next1053Ready' => $plans[1053]['next1053_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1038-1053 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1037_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1038-1053 self-test passed\n";
    return;
}

return $summary;
