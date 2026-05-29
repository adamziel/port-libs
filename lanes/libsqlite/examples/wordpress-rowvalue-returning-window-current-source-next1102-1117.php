<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1117', option_value || ':yield1117', bytes + 809) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1117', option_value || ':attempt1117', bytes + 691) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1117', option_value || ':retry1117', bytes + 823) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1102; $next <= 1117; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1102; $next <= 1117; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1102]['next1102_handoff']['next1101_ready'] === true);
assert($plans[1105]['next1105_ready'] === true);
assert($plans[1109]['next1109_ready'] === true);
assert($plans[1113]['next1113_ready'] === true);
assert($plans[1117]['next1117_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1102-1117',
    'candidateStatuses' => array_values($statuses),
    'next1102Handoff' => $plans[1102]['next1102_handoff']['next1102_handoff'],
    'next1102AfterReadyRange' => $plans[1102]['next1102_handoff']['after_ready_range'],
    'next1102ConsumesNext1101Ready' => $plans[1102]['next1102_handoff']['next1101_ready'],
    'next1103SourceAudit' => $plans[1103]['next1103_source_audit']['next1103_source_audit'],
    'next1103PreservesCurrentSource' => $plans[1103]['next1103_source_audit']['retry_rows_preserve_current_source'],
    'next1104Preflight' => $plans[1104]['next1104_preflight']['next1104_preflight'],
    'next1104KeepsThroughputHigh' => $plans[1104]['next1104_preflight']['keeps_libsqlite_throughput_high'],
    'next1105Final' => $plans[1105]['next1105_final']['next1105_final'],
    'next1105Ready' => $plans[1105]['next1105_ready'],
    'next1106Handoff' => $plans[1106]['next1106_handoff']['next1106_handoff'],
    'next1106AfterReadyRange' => $plans[1106]['next1106_handoff']['after_ready_range'],
    'next1109Ready' => $plans[1109]['next1109_ready'],
    'next1113Ready' => $plans[1113]['next1113_ready'],
    'next1117Final' => $plans[1117]['next1117_final']['next1117_final'],
    'next1117Ready' => $plans[1117]['next1117_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1102-1117 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1101_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1102-1117 self-test passed\n";
    return;
}

return $summary;
