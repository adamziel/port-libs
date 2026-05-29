<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield973', option_value || ':yield973', bytes + 661) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt973', option_value || ':attempt973', bytes + 561) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry973', option_value || ':retry973', bytes + 671) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 958; $next <= 973; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 958; $next <= 973; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[958]['next958_handoff']['next957_ready'] === true);
assert($plans[961]['next961_ready'] === true);
assert($plans[965]['next965_ready'] === true);
assert($plans[969]['next969_ready'] === true);
assert($plans[973]['next973_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next958-973',
    'candidateStatuses' => array_values($statuses),
    'next958Handoff' => $plans[958]['next958_handoff']['next958_handoff'],
    'next958AfterReadyRange' => $plans[958]['next958_handoff']['after_ready_range'],
    'next958ConsumesNext957Ready' => $plans[958]['next958_handoff']['next957_ready'],
    'next959SourceAudit' => $plans[959]['next959_source_audit']['next959_source_audit'],
    'next959PreservesCurrentSource' => $plans[959]['next959_source_audit']['retry_rows_preserve_current_source'],
    'next960Preflight' => $plans[960]['next960_preflight']['next960_preflight'],
    'next960KeepsThroughputHigh' => $plans[960]['next960_preflight']['keeps_libsqlite_throughput_high'],
    'next961Final' => $plans[961]['next961_final']['next961_final'],
    'next961Ready' => $plans[961]['next961_ready'],
    'next962Handoff' => $plans[962]['next962_handoff']['next962_handoff'],
    'next962AfterReadyRange' => $plans[962]['next962_handoff']['after_ready_range'],
    'next965Ready' => $plans[965]['next965_ready'],
    'next969Ready' => $plans[969]['next969_ready'],
    'next973Final' => $plans[973]['next973_final']['next973_final'],
    'next973Ready' => $plans[973]['next973_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next958-973 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next957_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next958-973 self-test passed\n";
    return;
}

return $summary;
