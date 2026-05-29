<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1101', option_value || ':yield1101', bytes + 773) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1101', option_value || ':attempt1101', bytes + 659) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1101', option_value || ':retry1101', bytes + 787) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1086; $next <= 1101; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1086; $next <= 1101; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1086]['next1086_handoff']['next1085_ready'] === true);
assert($plans[1089]['next1089_ready'] === true);
assert($plans[1093]['next1093_ready'] === true);
assert($plans[1097]['next1097_ready'] === true);
assert($plans[1101]['next1101_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1086-1101',
    'candidateStatuses' => array_values($statuses),
    'next1086Handoff' => $plans[1086]['next1086_handoff']['next1086_handoff'],
    'next1086AfterReadyRange' => $plans[1086]['next1086_handoff']['after_ready_range'],
    'next1086ConsumesNext1085Ready' => $plans[1086]['next1086_handoff']['next1085_ready'],
    'next1087SourceAudit' => $plans[1087]['next1087_source_audit']['next1087_source_audit'],
    'next1087PreservesCurrentSource' => $plans[1087]['next1087_source_audit']['retry_rows_preserve_current_source'],
    'next1088Preflight' => $plans[1088]['next1088_preflight']['next1088_preflight'],
    'next1088KeepsThroughputHigh' => $plans[1088]['next1088_preflight']['keeps_libsqlite_throughput_high'],
    'next1089Final' => $plans[1089]['next1089_final']['next1089_final'],
    'next1089Ready' => $plans[1089]['next1089_ready'],
    'next1090Handoff' => $plans[1090]['next1090_handoff']['next1090_handoff'],
    'next1090AfterReadyRange' => $plans[1090]['next1090_handoff']['after_ready_range'],
    'next1093Ready' => $plans[1093]['next1093_ready'],
    'next1097Ready' => $plans[1097]['next1097_ready'],
    'next1101Final' => $plans[1101]['next1101_final']['next1101_final'],
    'next1101Ready' => $plans[1101]['next1101_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1086-1101 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1085_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1086-1101 self-test passed\n";
    return;
}

return $summary;
