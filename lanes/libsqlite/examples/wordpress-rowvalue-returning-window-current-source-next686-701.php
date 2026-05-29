<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield701', option_value || ':yield701', bytes + 401) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt701', option_value || ':attempt701', bytes + 301) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry701', option_value || ':retry701', bytes + 411) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 686; $next <= 701; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 686; $next <= 701; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[686]['next686_handoff']['next685_ready'] === true);
assert($plans[689]['next689_ready'] === true);
assert($plans[693]['next693_ready'] === true);
assert($plans[697]['next697_ready'] === true);
assert($plans[701]['next701_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next686-701',
    'candidateStatuses' => array_values($statuses),
    'next686Handoff' => $plans[686]['next686_handoff']['next686_handoff'],
    'next686AfterReadyRange' => $plans[686]['next686_handoff']['after_ready_range'],
    'next686ConsumesNext685Ready' => $plans[686]['next686_handoff']['next685_ready'],
    'next687SourceAudit' => $plans[687]['next687_source_audit']['next687_source_audit'],
    'next687PreservesCurrentSource' => $plans[687]['next687_source_audit']['retry_rows_preserve_current_source'],
    'next688Preflight' => $plans[688]['next688_preflight']['next688_preflight'],
    'next688KeepsThroughputHigh' => $plans[688]['next688_preflight']['keeps_libsqlite_throughput_high'],
    'next689Final' => $plans[689]['next689_final']['next689_final'],
    'next689Ready' => $plans[689]['next689_ready'],
    'next690Handoff' => $plans[690]['next690_handoff']['next690_handoff'],
    'next690AfterReadyRange' => $plans[690]['next690_handoff']['after_ready_range'],
    'next693Ready' => $plans[693]['next693_ready'],
    'next697Ready' => $plans[697]['next697_ready'],
    'next701Final' => $plans[701]['next701_final']['next701_final'],
    'next701Ready' => $plans[701]['next701_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next686-701 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next670-685 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next686-701 self-test passed\n";
    return;
}

return $summary;
