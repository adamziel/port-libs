<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1021', option_value || ':yield1021', bytes + 709) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1021', option_value || ':attempt1021', bytes + 609) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1021', option_value || ':retry1021', bytes + 719) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1006; $next <= 1021; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1006; $next <= 1021; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1006]['next1006_handoff']['next1005_ready'] === true);
assert($plans[1009]['next1009_ready'] === true);
assert($plans[1013]['next1013_ready'] === true);
assert($plans[1017]['next1017_ready'] === true);
assert($plans[1021]['next1021_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1006-1021',
    'candidateStatuses' => array_values($statuses),
    'next1006Handoff' => $plans[1006]['next1006_handoff']['next1006_handoff'],
    'next1006AfterReadyRange' => $plans[1006]['next1006_handoff']['after_ready_range'],
    'next1006ConsumesNext1005Ready' => $plans[1006]['next1006_handoff']['next1005_ready'],
    'next1007SourceAudit' => $plans[1007]['next1007_source_audit']['next1007_source_audit'],
    'next1007PreservesCurrentSource' => $plans[1007]['next1007_source_audit']['retry_rows_preserve_current_source'],
    'next1008Preflight' => $plans[1008]['next1008_preflight']['next1008_preflight'],
    'next1008KeepsThroughputHigh' => $plans[1008]['next1008_preflight']['keeps_libsqlite_throughput_high'],
    'next1009Final' => $plans[1009]['next1009_final']['next1009_final'],
    'next1009Ready' => $plans[1009]['next1009_ready'],
    'next1010Handoff' => $plans[1010]['next1010_handoff']['next1010_handoff'],
    'next1010AfterReadyRange' => $plans[1010]['next1010_handoff']['after_ready_range'],
    'next1013Ready' => $plans[1013]['next1013_ready'],
    'next1017Ready' => $plans[1017]['next1017_ready'],
    'next1021Final' => $plans[1021]['next1021_final']['next1021_final'],
    'next1021Ready' => $plans[1021]['next1021_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1006-1021 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1005_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1006-1021 self-test passed\n";
    return;
}

return $summary;
