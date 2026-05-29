<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield717', option_value || ':yield717', bytes + 417) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt717', option_value || ':attempt717', bytes + 317) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry717', option_value || ':retry717', bytes + 427) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 702; $next <= 717; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 702; $next <= 717; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[702]['next702_handoff']['next701_ready'] === true);
assert($plans[705]['next705_ready'] === true);
assert($plans[709]['next709_ready'] === true);
assert($plans[713]['next713_ready'] === true);
assert($plans[717]['next717_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next702-717',
    'candidateStatuses' => array_values($statuses),
    'next702Handoff' => $plans[702]['next702_handoff']['next702_handoff'],
    'next702AfterReadyRange' => $plans[702]['next702_handoff']['after_ready_range'],
    'next702ConsumesNext701Ready' => $plans[702]['next702_handoff']['next701_ready'],
    'next703SourceAudit' => $plans[703]['next703_source_audit']['next703_source_audit'],
    'next703PreservesCurrentSource' => $plans[703]['next703_source_audit']['retry_rows_preserve_current_source'],
    'next704Preflight' => $plans[704]['next704_preflight']['next704_preflight'],
    'next704KeepsThroughputHigh' => $plans[704]['next704_preflight']['keeps_libsqlite_throughput_high'],
    'next705Final' => $plans[705]['next705_final']['next705_final'],
    'next705Ready' => $plans[705]['next705_ready'],
    'next706Handoff' => $plans[706]['next706_handoff']['next706_handoff'],
    'next706AfterReadyRange' => $plans[706]['next706_handoff']['after_ready_range'],
    'next709Ready' => $plans[709]['next709_ready'],
    'next713Ready' => $plans[713]['next713_ready'],
    'next717Final' => $plans[717]['next717_final']['next717_final'],
    'next717Ready' => $plans[717]['next717_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next702-717 row-value UPDATE/DELETE RETURNING window current-source continuation after integrated next686-701 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next702-717 self-test passed\n";
    return;
}

return $summary;
