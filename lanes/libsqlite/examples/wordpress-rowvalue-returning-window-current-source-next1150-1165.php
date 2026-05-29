<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
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
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
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
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1150-1165',
    'candidateStatuses' => array_values($statuses),
    'next1150Handoff' => $plans[1150]['next1150_handoff']['next1150_handoff'],
    'next1150AfterReadyRange' => $plans[1150]['next1150_handoff']['after_ready_range'],
    'next1150ConsumesNext1149Ready' => $plans[1150]['next1150_handoff']['next1149_ready'],
    'next1151SourceAudit' => $plans[1151]['next1151_source_audit']['next1151_source_audit'],
    'next1151PreservesCurrentSource' => $plans[1151]['next1151_source_audit']['retry_rows_preserve_current_source'],
    'next1152Preflight' => $plans[1152]['next1152_preflight']['next1152_preflight'],
    'next1152KeepsThroughputHigh' => $plans[1152]['next1152_preflight']['keeps_libsqlite_throughput_high'],
    'next1153Final' => $plans[1153]['next1153_final']['next1153_final'],
    'next1153Ready' => $plans[1153]['next1153_ready'],
    'next1154Handoff' => $plans[1154]['next1154_handoff']['next1154_handoff'],
    'next1154AfterReadyRange' => $plans[1154]['next1154_handoff']['after_ready_range'],
    'next1157Ready' => $plans[1157]['next1157_ready'],
    'next1161Ready' => $plans[1161]['next1161_ready'],
    'next1165Final' => $plans[1165]['next1165_final']['next1165_final'],
    'next1165Ready' => $plans[1165]['next1165_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1150-1165 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1149_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1150-1165 self-test passed\n";
    return;
}

return $summary;
