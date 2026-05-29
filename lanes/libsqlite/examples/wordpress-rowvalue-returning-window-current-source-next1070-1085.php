<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1085', option_value || ':yield1085', bytes + 761) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1085', option_value || ':attempt1085', bytes + 647) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1085', option_value || ':retry1085', bytes + 769) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1070; $next <= 1085; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1070; $next <= 1085; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1070]['next1070_handoff']['next1069_ready'] === true);
assert($plans[1073]['next1073_ready'] === true);
assert($plans[1077]['next1077_ready'] === true);
assert($plans[1081]['next1081_ready'] === true);
assert($plans[1085]['next1085_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1070-1085',
    'candidateStatuses' => array_values($statuses),
    'next1070Handoff' => $plans[1070]['next1070_handoff']['next1070_handoff'],
    'next1070AfterReadyRange' => $plans[1070]['next1070_handoff']['after_ready_range'],
    'next1070ConsumesNext1069Ready' => $plans[1070]['next1070_handoff']['next1069_ready'],
    'next1071SourceAudit' => $plans[1071]['next1071_source_audit']['next1071_source_audit'],
    'next1071PreservesCurrentSource' => $plans[1071]['next1071_source_audit']['retry_rows_preserve_current_source'],
    'next1072Preflight' => $plans[1072]['next1072_preflight']['next1072_preflight'],
    'next1072KeepsThroughputHigh' => $plans[1072]['next1072_preflight']['keeps_libsqlite_throughput_high'],
    'next1073Final' => $plans[1073]['next1073_final']['next1073_final'],
    'next1073Ready' => $plans[1073]['next1073_ready'],
    'next1074Handoff' => $plans[1074]['next1074_handoff']['next1074_handoff'],
    'next1074AfterReadyRange' => $plans[1074]['next1074_handoff']['after_ready_range'],
    'next1077Ready' => $plans[1077]['next1077_ready'],
    'next1081Ready' => $plans[1081]['next1081_ready'],
    'next1085Final' => $plans[1085]['next1085_final']['next1085_final'],
    'next1085Ready' => $plans[1085]['next1085_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1070-1085 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1069_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1070-1085 self-test passed\n";
    return;
}

return $summary;
