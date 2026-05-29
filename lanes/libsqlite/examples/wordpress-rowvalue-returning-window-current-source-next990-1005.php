<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1005', option_value || ':yield1005', bytes + 693) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1005', option_value || ':attempt1005', bytes + 593) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1005', option_value || ':retry1005', bytes + 703) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 990; $next <= 1005; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 990; $next <= 1005; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[990]['next990_handoff']['next989_ready'] === true);
assert($plans[993]['next993_ready'] === true);
assert($plans[997]['next997_ready'] === true);
assert($plans[1001]['next1001_ready'] === true);
assert($plans[1005]['next1005_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next990-1005',
    'candidateStatuses' => array_values($statuses),
    'next990Handoff' => $plans[990]['next990_handoff']['next990_handoff'],
    'next990AfterReadyRange' => $plans[990]['next990_handoff']['after_ready_range'],
    'next990ConsumesNext989Ready' => $plans[990]['next990_handoff']['next989_ready'],
    'next991SourceAudit' => $plans[991]['next991_source_audit']['next991_source_audit'],
    'next991PreservesCurrentSource' => $plans[991]['next991_source_audit']['retry_rows_preserve_current_source'],
    'next992Preflight' => $plans[992]['next992_preflight']['next992_preflight'],
    'next992KeepsThroughputHigh' => $plans[992]['next992_preflight']['keeps_libsqlite_throughput_high'],
    'next993Final' => $plans[993]['next993_final']['next993_final'],
    'next993Ready' => $plans[993]['next993_ready'],
    'next994Handoff' => $plans[994]['next994_handoff']['next994_handoff'],
    'next994AfterReadyRange' => $plans[994]['next994_handoff']['after_ready_range'],
    'next997Ready' => $plans[997]['next997_ready'],
    'next1001Ready' => $plans[1001]['next1001_ready'],
    'next1005Final' => $plans[1005]['next1005_final']['next1005_final'],
    'next1005Ready' => $plans[1005]['next1005_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next990-1005 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next989_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next990-1005 self-test passed\n";
    return;
}

return $summary;
