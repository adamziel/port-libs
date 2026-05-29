<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1133', option_value || ':yield1133', bytes + 827) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1133', option_value || ':attempt1133', bytes + 709) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1133', option_value || ':retry1133', bytes + 839) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1118; $next <= 1133; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1118; $next <= 1133; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1118]['next1118_handoff']['next1117_ready'] === true);
assert($plans[1121]['next1121_ready'] === true);
assert($plans[1125]['next1125_ready'] === true);
assert($plans[1129]['next1129_ready'] === true);
assert($plans[1133]['next1133_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1118-1133',
    'candidateStatuses' => array_values($statuses),
    'next1118Handoff' => $plans[1118]['next1118_handoff']['next1118_handoff'],
    'next1118AfterReadyRange' => $plans[1118]['next1118_handoff']['after_ready_range'],
    'next1118ConsumesNext1117Ready' => $plans[1118]['next1118_handoff']['next1117_ready'],
    'next1119SourceAudit' => $plans[1119]['next1119_source_audit']['next1119_source_audit'],
    'next1119PreservesCurrentSource' => $plans[1119]['next1119_source_audit']['retry_rows_preserve_current_source'],
    'next1120Preflight' => $plans[1120]['next1120_preflight']['next1120_preflight'],
    'next1120KeepsThroughputHigh' => $plans[1120]['next1120_preflight']['keeps_libsqlite_throughput_high'],
    'next1121Final' => $plans[1121]['next1121_final']['next1121_final'],
    'next1121Ready' => $plans[1121]['next1121_ready'],
    'next1122Handoff' => $plans[1122]['next1122_handoff']['next1122_handoff'],
    'next1122AfterReadyRange' => $plans[1122]['next1122_handoff']['after_ready_range'],
    'next1125Ready' => $plans[1125]['next1125_ready'],
    'next1129Ready' => $plans[1129]['next1129_ready'],
    'next1133Final' => $plans[1133]['next1133_final']['next1133_final'],
    'next1133Ready' => $plans[1133]['next1133_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1118-1133 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1117_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1118-1133 self-test passed\n";
    return;
}

return $summary;
